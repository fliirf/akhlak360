<?php

namespace App\Http\Controllers\AssessmentCycle;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentPeriod;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssessmentAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'assessment_period_id' => ['nullable', 'integer', 'exists:assessment_periods,id'],
            'status' => ['nullable', Rule::in(['pending', 'submitted'])],
            'assessor_type' => ['nullable', Rule::in(['supervisor', 'peer', 'subordinate', 'self'])],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
        ]);
        $query = AssessmentAssignment::query()
            ->with([
                'assessmentPeriod',
                'assessor.department',
                'assessor.position',
                'assessee.department',
                'assessee.position',
            ])
            ->when($request->filled('assessment_period_id'), fn (Builder $query) => $query->where('assessment_period_id', $request->integer('assessment_period_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->status))
            ->when($request->filled('assessor_type'), fn (Builder $query) => $query->where('assessor_type', $request->assessor_type))
            ->when($request->filled('department_id'), fn (Builder $query) => $query
                ->whereHas('assessee', fn (Builder $employeeQuery) => $employeeQuery->where('department_id', $request->integer('department_id'))));
        $assignments = (clone $query)->latest()
            ->paginate(10)
            ->withQueryString();

        return view('assessment-cycle.assignments.index', [
            'assignments' => $assignments,
            'periods' => AssessmentPeriod::orderByDesc('year')->orderByDesc('start_date')->get(),
            'departments' => Department::orderBy('name')->get(),
            'activePeriod' => AssessmentPeriod::active()->first(),
            'summary' => [
                'total' => (clone $query)->count(),
                'pending' => (clone $query)->pending()->count(),
                'submitted' => (clone $query)->submitted()->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('assessment-cycle.assignments.create', [
            'assignment' => new AssessmentAssignment([
                'assessment_period_id' => AssessmentPeriod::open()->value('id'),
                'assessor_type' => 'peer',
                'status' => 'pending',
            ]),
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $error = $this->businessRuleError($data);

        if ($error) {
            return back()->withInput()->withErrors($error);
        }

        $assignment = AssessmentAssignment::create([
            ...$data,
            'status' => 'pending',
        ]);

        $this->audit($request, 'create', "Created {$assignment->assessor_type} assignment #{$assignment->id}.");

        return redirect()
            ->route('assessment-cycle.assign-assessors.index')
            ->with('success', 'Assessment assignment created successfully.');
    }

    public function edit(AssessmentAssignment $assignment): View|RedirectResponse
    {
        if ($assignment->status !== 'pending') {
            return redirect()
                ->route('assessment-cycle.assign-assessors.index')
                ->with('warning', 'Submitted assignments cannot be edited.');
        }

        return view('assessment-cycle.assignments.edit', [
            'assignment' => $assignment,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, AssessmentAssignment $assignment): RedirectResponse
    {
        if ($assignment->status !== 'pending') {
            return redirect()
                ->route('assessment-cycle.assign-assessors.index')
                ->with('warning', 'Submitted assignments cannot be edited.');
        }

        $data = $this->validatedData($request, $assignment);
        $error = $this->businessRuleError($data, $assignment);

        if ($error) {
            return back()->withInput()->withErrors($error);
        }

        $assignment->update([
            ...$data,
            'status' => 'pending',
        ]);

        $this->audit($request, 'update', "Updated {$assignment->assessor_type} assignment #{$assignment->id}.");

        return redirect()
            ->route('assessment-cycle.assign-assessors.index')
            ->with('success', 'Assessment assignment updated successfully.');
    }

    public function destroy(Request $request, AssessmentAssignment $assignment): RedirectResponse
    {
        if ($assignment->status !== 'pending') {
            return redirect()
                ->route('assessment-cycle.assign-assessors.index')
                ->with('warning', 'Submitted assignments cannot be deleted.');
        }

        $id = $assignment->id;
        $assignment->delete();

        $this->audit($request, 'delete', "Deleted pending assignment #{$id}.");

        return redirect()
            ->route('assessment-cycle.assign-assessors.index')
            ->with('success', 'Assessment assignment deleted successfully.');
    }

    public function generateSelf(Request $request): RedirectResponse
    {
        $period = $this->activePeriodFromRequest($request);
        $created = 0;

        Employee::active()->each(function (Employee $employee) use ($period, &$created): void {
            $assignment = AssessmentAssignment::firstOrCreate([
                'assessment_period_id' => $period->id,
                'assessor_employee_id' => $employee->id,
                'assessee_employee_id' => $employee->id,
                'assessor_type' => 'self',
            ], [
                'status' => 'pending',
            ]);

            $created += $assignment->wasRecentlyCreated ? 1 : 0;
        });

        $this->audit($request, 'generate_self', "Generated {$created} self assignments for {$period->name}.");

        return redirect()
            ->route('assessment-cycle.assign-assessors.index', ['assessment_period_id' => $period->id])
            ->with('success', "{$created} self assignments generated.");
    }

    public function generateSupervisor(Request $request): RedirectResponse
    {
        $period = $this->activePeriodFromRequest($request);
        $created = 0;

        Employee::active()
            ->whereNotNull('supervisor_id')
            ->with('supervisor')
            ->get()
            ->each(function (Employee $employee) use ($period, &$created): void {
                if ($employee->supervisor?->employment_status !== 'active') {
                    return;
                }

                $assignment = AssessmentAssignment::firstOrCreate([
                    'assessment_period_id' => $period->id,
                    'assessor_employee_id' => $employee->supervisor_id,
                    'assessee_employee_id' => $employee->id,
                    'assessor_type' => 'supervisor',
                ], [
                    'status' => 'pending',
                ]);

                $created += $assignment->wasRecentlyCreated ? 1 : 0;
            });

        $this->audit($request, 'generate_supervisor', "Generated {$created} supervisor assignments for {$period->name}.");

        return redirect()
            ->route('assessment-cycle.assign-assessors.index', ['assessment_period_id' => $period->id])
            ->with('success', "{$created} supervisor assignments generated.");
    }

    public function generateSubordinate(Request $request): RedirectResponse
    {
        $period = $this->activePeriodFromRequest($request);
        $created = 0;

        Employee::active()
            ->whereHas('subordinates', fn (Builder $query) => $query->active())
            ->with(['subordinates' => fn ($query) => $query->active()])
            ->get()
            ->each(function (Employee $leader) use ($period, &$created): void {
                foreach ($leader->subordinates as $subordinate) {
                    $assignment = AssessmentAssignment::firstOrCreate([
                        'assessment_period_id' => $period->id,
                        'assessor_employee_id' => $subordinate->id,
                        'assessee_employee_id' => $leader->id,
                        'assessor_type' => 'subordinate',
                    ], [
                        'status' => 'pending',
                    ]);

                    $created += $assignment->wasRecentlyCreated ? 1 : 0;
                }
            });

        $this->audit($request, 'generate_subordinate', "Generated {$created} subordinate assignments for {$period->name}.");

        return redirect()
            ->route('assessment-cycle.assign-assessors.index', ['assessment_period_id' => $period->id])
            ->with('success', "{$created} subordinate assignments generated.");
    }

    private function validatedData(Request $request, ?AssessmentAssignment $assignment = null): array
    {
        return $request->validate([
            'assessment_period_id' => [
                'required',
                Rule::exists('assessment_periods', 'id')->where(fn ($query) => $query
                    ->where('status', 'active')
                    ->whereDate('start_date', '<=', today())
                    ->whereDate('end_date', '>=', today())),
            ],
            'assessor_employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('employment_status', 'active'),
            ],
            'assessee_employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('employment_status', 'active'),
            ],
            'assessor_type' => ['required', Rule::in(['supervisor', 'peer', 'subordinate', 'self'])],
        ]);
    }

    private function businessRuleError(array $data, ?AssessmentAssignment $assignment = null): array
    {
        $sameEmployee = (int) $data['assessor_employee_id'] === (int) $data['assessee_employee_id'];

        if ($data['assessor_type'] === 'self' && ! $sameEmployee) {
            return ['assessor_employee_id' => 'For self assignments, assessor must equal assessee.'];
        }

        if ($data['assessor_type'] !== 'self' && $sameEmployee) {
            return ['assessor_employee_id' => 'Assessor and assessee cannot be the same except self assignments.'];
        }

        $duplicateExists = AssessmentAssignment::query()
            ->where('assessment_period_id', $data['assessment_period_id'])
            ->where('assessor_employee_id', $data['assessor_employee_id'])
            ->where('assessee_employee_id', $data['assessee_employee_id'])
            ->where('assessor_type', $data['assessor_type'])
            ->when($assignment, fn (Builder $query) => $query->whereKeyNot($assignment->id))
            ->exists();

        if ($duplicateExists) {
            return ['assessor_employee_id' => 'Duplicate assignment already exists for this period, assessor, assessee, and type.'];
        }

        return [];
    }

    private function activePeriodFromRequest(Request $request): AssessmentPeriod
    {
        $data = $request->validate([
            'assessment_period_id' => ['nullable', 'exists:assessment_periods,id'],
        ]);

        $period = isset($data['assessment_period_id'])
            ? AssessmentPeriod::open()->whereKey($data['assessment_period_id'])->first()
            : AssessmentPeriod::open()->first();

        abort_if(! $period, 422, 'Select an assessment period that is currently open.');

        return $period;
    }

    private function formOptions(): array
    {
        return [
            'periods' => AssessmentPeriod::open()->orderByDesc('year')->orderByDesc('start_date')->get(),
            'employees' => Employee::active()->with(['department', 'position'])->orderBy('name')->get(),
        ];
    }

    private function audit(Request $request, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module' => 'assessment_assignments',
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
