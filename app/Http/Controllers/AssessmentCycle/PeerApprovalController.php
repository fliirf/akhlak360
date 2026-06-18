<?php

namespace App\Http\Controllers\AssessmentCycle;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentPeriod;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PeerApproval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PeerApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'assessment_period_id' => ['nullable', 'integer', 'exists:assessment_periods,id'],
        ]);
        $query = PeerApproval::query()
            ->with(['assessmentPeriod', 'employee.department', 'peerEmployee', 'supervisorEmployee'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('assessment_period_id'), fn ($query) => $query->where('assessment_period_id', $request->integer('assessment_period_id')));

        if ($request->user()->role === 'supervisor') {
            $supervisorEmployeeId = $request->user()->employee?->id;
            $query->where('supervisor_employee_id', $supervisorEmployeeId);

            if (! $request->filled('status')) {
                $query->pending();
            }
        }

        $peerApprovals = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('assessment-cycle.peer-approvals.index', [
            'peerApprovals' => $peerApprovals,
            'periods' => AssessmentPeriod::orderByDesc('year')->orderByDesc('start_date')->get(),
            'activePeriod' => AssessmentPeriod::active()->first(),
            'employees' => Employee::active()->with(['department', 'supervisor'])->orderBy('name')->get(),
            'summary' => [
                'pending' => (clone $query)->pending()->count(),
                'approved' => (clone $query)->where('status', 'approved')->count(),
                'rejected' => (clone $query)->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin_hr'), 403);

        $data = $request->validate([
            'assessment_period_id' => ['required', 'exists:assessment_periods,id'],
            'employee_id' => ['required', 'exists:employees,id'],
            'peer_employee_id' => ['required', 'exists:employees,id', 'different:employee_id'],
            'notes' => ['nullable', 'string'],
        ]);

        $period = AssessmentPeriod::active()->whereKey($data['assessment_period_id'])->first();

        if (! $period) {
            return back()
                ->withInput()
                ->withErrors(['assessment_period_id' => 'Peer assessors can only be proposed for an active period.']);
        }

        $employee = Employee::with('supervisor')->findOrFail($data['employee_id']);

        if (! $employee->supervisor_id) {
            return back()
                ->withInput()
                ->withErrors(['employee_id' => 'Selected employee does not have a supervisor.']);
        }

        $approval = PeerApproval::updateOrCreate([
            'assessment_period_id' => $period->id,
            'employee_id' => $employee->id,
            'peer_employee_id' => $data['peer_employee_id'],
        ], [
            'supervisor_employee_id' => $employee->supervisor_id,
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
            'approved_at' => null,
        ]);

        $this->audit($request, 'propose', "Proposed peer assessor {$approval->peerEmployee?->name} for {$employee->name}.");

        return redirect()
            ->route('assessment-cycle.peer-approval.index')
            ->with('success', 'Peer assessor proposed for supervisor approval.');
    }

    public function approve(Request $request, PeerApproval $peerApproval): RedirectResponse
    {
        $this->authorizeSupervisor($request, $peerApproval);

        $data = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $peerApproval->update([
            'status' => 'approved',
            'notes' => $data['notes'] ?? $peerApproval->notes,
            'approved_at' => now(),
        ]);

        AssessmentAssignment::updateOrCreate([
            'assessment_period_id' => $peerApproval->assessment_period_id,
            'assessor_employee_id' => $peerApproval->peer_employee_id,
            'assessee_employee_id' => $peerApproval->employee_id,
            'assessor_type' => 'peer',
        ], [
            'status' => 'pending',
        ]);

        $this->audit($request, 'approve', "Approved peer assessor {$peerApproval->peerEmployee?->name} for {$peerApproval->employee?->name}.");

        return redirect()
            ->route('assessment-cycle.peer-approval.index')
            ->with('success', 'Peer approval approved and peer assessment assignment created.');
    }

    public function reject(Request $request, PeerApproval $peerApproval): RedirectResponse
    {
        $this->authorizeSupervisor($request, $peerApproval);

        $data = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $peerApproval->update([
            'status' => 'rejected',
            'notes' => $data['notes'] ?? $peerApproval->notes,
            'approved_at' => null,
        ]);

        $this->audit($request, 'reject', "Rejected peer assessor {$peerApproval->peerEmployee?->name} for {$peerApproval->employee?->name}.");

        return redirect()
            ->route('assessment-cycle.peer-approval.index')
            ->with('warning', 'Peer approval rejected.');
    }

    private function authorizeSupervisor(Request $request, PeerApproval $peerApproval): void
    {
        abort_unless($request->user()->hasRole('supervisor'), 403);
        abort_unless($request->user()->employee?->id === $peerApproval->supervisor_employee_id, 403);
    }

    private function audit(Request $request, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module' => 'peer_approvals',
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
