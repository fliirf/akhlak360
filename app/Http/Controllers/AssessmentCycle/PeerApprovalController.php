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
use Illuminate\Support\Facades\DB;
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
            'activePeriod' => AssessmentPeriod::open()->first(),
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
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('employment_status', 'active'),
            ],
            'peer_employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('employment_status', 'active'),
                'different:employee_id',
            ],
            'notes' => ['nullable', 'string'],
        ]);

        $period = AssessmentPeriod::open()->whereKey($data['assessment_period_id'])->first();

        if (! $period) {
            return back()
                ->withInput()
                ->withErrors(['assessment_period_id' => 'Peer assessors can only be proposed while the assessment period is open.']);
        }

        $employee = Employee::with('supervisor')->findOrFail($data['employee_id']);

        if (! $employee->supervisor_id || $employee->supervisor?->employment_status !== 'active') {
            return back()
                ->withInput()
                ->withErrors(['employee_id' => 'Selected employee does not have an active supervisor.']);
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

        $peerApproval->loadMissing(['assessmentPeriod', 'employee', 'peerEmployee']);
        if ($peerApproval->status !== 'pending') {
            return back()->with('warning', 'Only pending peer proposals can be approved.');
        }

        if (! $peerApproval->assessmentPeriod?->isOpen()) {
            return back()->with('warning', 'This peer proposal cannot be approved because its assessment period is not open.');
        }

        if (
            $peerApproval->employee?->employment_status !== 'active'
            || $peerApproval->peerEmployee?->employment_status !== 'active'
        ) {
            return back()->with('warning', 'This peer proposal contains an inactive employee and cannot be approved.');
        }

        DB::transaction(function () use ($peerApproval, $data): void {
            $peerApproval->update([
                'status' => 'approved',
                'notes' => $data['notes'] ?? $peerApproval->notes,
                'approved_at' => now(),
            ]);

            AssessmentAssignment::firstOrCreate([
                'assessment_period_id' => $peerApproval->assessment_period_id,
                'assessor_employee_id' => $peerApproval->peer_employee_id,
                'assessee_employee_id' => $peerApproval->employee_id,
                'assessor_type' => 'peer',
            ], [
                'status' => 'pending',
            ]);
        });

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

        $peerApproval->loadMissing('assessmentPeriod');
        if ($peerApproval->status !== 'pending') {
            return back()->with('warning', 'Only pending peer proposals can be rejected.');
        }

        if (! $peerApproval->assessmentPeriod?->isOpen()) {
            return back()->with('warning', 'This peer proposal cannot be rejected because its assessment period is not open.');
        }

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
