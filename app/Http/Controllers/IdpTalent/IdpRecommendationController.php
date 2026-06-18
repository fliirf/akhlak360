<?php

namespace App\Http\Controllers\IdpTalent;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\IdpRecommendation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IdpRecommendationController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'period_id' => ['nullable', 'integer', 'exists:assessment_periods,id'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'status' => ['nullable', Rule::in($this->statuses())],
        ]);

        if ($request->user()->hasRole('management')) {
            return $this->summary($request);
        }

        $periods = AssessmentPeriod::orderByDesc('year')->orderByDesc('start_date')->get();
        $departments = Department::active()->orderBy('name')->get();

        $recommendations = IdpRecommendation::query()
            ->with(['assessmentPeriod', 'employee.department'])
            ->when($request->user()->hasRole('employee'), fn (Builder $query) => $query->where('employee_id', $request->user()->employee?->id ?? 0))
            ->when($request->user()->hasRole('supervisor'), fn (Builder $query) => $query->whereHas(
                'employee',
                fn (Builder $employeeQuery) => $employeeQuery->where('supervisor_id', $request->user()->employee?->id ?? 0)
            ))
            ->when($request->filled('period_id'), fn (Builder $query) => $query->where('assessment_period_id', $request->integer('period_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->status))
            ->when($request->filled('department_id'), fn (Builder $query) => $query->whereHas(
                'employee',
                fn (Builder $employeeQuery) => $employeeQuery->where('department_id', $request->integer('department_id'))
            ))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('idp-talent.idp-recommendations.index', [
            'periods' => $periods,
            'departments' => $departments,
            'recommendations' => $recommendations,
            'statuses' => $this->statuses(),
            'canEdit' => $request->user()->hasRole('admin_hr'),
        ]);
    }

    public function edit(Request $request, IdpRecommendation $idpRecommendation): View
    {
        abort_unless($request->user()->hasRole('admin_hr'), 403);

        $idpRecommendation->load(['assessmentPeriod', 'employee.department']);

        return view('idp-talent.idp-recommendations.edit', [
            'recommendation' => $idpRecommendation,
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(Request $request, IdpRecommendation $idpRecommendation): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin_hr'), 403);

        $data = $request->validate([
            'action_plan' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in($this->statuses())],
        ]);

        $idpRecommendation->update($data);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'update',
            'module' => 'idp_recommendations',
            'description' => "Updated IDP recommendation #{$idpRecommendation->id} for employee #{$idpRecommendation->employee_id}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('idp-talent.idp-recommendations.index')
            ->with('success', 'IDP recommendation updated successfully.');
    }

    private function summary(Request $request): View
    {
        $periods = AssessmentPeriod::orderByDesc('year')->orderByDesc('start_date')->get();
        $departments = Department::active()->orderBy('name')->get();
        $query = IdpRecommendation::query()
            ->with(['employee.department', 'assessmentPeriod'])
            ->when($request->filled('period_id'), fn (Builder $query) => $query->where('assessment_period_id', $request->integer('period_id')))
            ->when($request->filled('department_id'), fn (Builder $query) => $query->whereHas(
                'employee',
                fn (Builder $employeeQuery) => $employeeQuery->where('department_id', $request->integer('department_id'))
            ));

        $items = $query->get();

        return view('idp-talent.idp-recommendations.summary', [
            'periods' => $periods,
            'departments' => $departments,
            'summary' => [
                'total' => $items->count(),
                'open' => $items->whereIn('status', ['draft', 'approved', 'in_progress'])->count(),
                'completed' => $items->where('status', 'completed')->count(),
                'overdue' => $items->filter(fn (IdpRecommendation $item) => $item->due_date && $item->due_date->isPast() && $item->status !== 'completed')->count(),
            ],
            'statusChart' => [
                'labels' => $items->groupBy('status')->keys()->map(fn ($status) => ucfirst(str_replace('_', ' ', $status)))->values()->all(),
                'data' => $items->groupBy('status')->map->count()->values()->all(),
            ],
            'coreValueChart' => [
                'labels' => $items->groupBy('weakest_core_value')->keys()->values()->all(),
                'data' => $items->groupBy('weakest_core_value')->map->count()->values()->all(),
            ],
        ]);
    }

    private function statuses(): array
    {
        return ['draft', 'approved', 'in_progress', 'completed'];
    }
}
