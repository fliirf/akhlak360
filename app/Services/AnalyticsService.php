<?php

namespace App\Services;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentResult;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AnalyticsService
{
    public const CORE_VALUES = [
        'Amanah' => 'amanah_score',
        'Kompeten' => 'kompeten_score',
        'Harmonis' => 'harmonis_score',
        'Loyal' => 'loyal_score',
        'Adaptif' => 'adaptif_score',
        'Kolaboratif' => 'kolaboratif_score',
    ];

    public function periods(): Collection
    {
        return AssessmentPeriod::orderByDesc('year')->orderByDesc('start_date')->get();
    }

    public function departments(): Collection
    {
        return Department::active()->orderBy('name')->get();
    }

    public function defaultPeriodId(Collection $periods): ?int
    {
        return ($periods->firstWhere('status', 'active') ?? $periods->first())?->id;
    }

    public function resultQuery(?int $periodId, ?int $departmentId = null): Builder
    {
        return AssessmentResult::query()
            ->with(['employee.department', 'assessmentPeriod'])
            ->when($periodId, fn (Builder $query) => $query->where('assessment_period_id', $periodId))
            ->when($departmentId, fn (Builder $query) => $query->whereHas(
                'employee',
                fn (Builder $employeeQuery) => $employeeQuery->where('department_id', $departmentId)
            ));
    }

    public function coreValueAverages(Builder $query): array
    {
        $row = $query->selectRaw(collect(self::CORE_VALUES)
            ->map(fn (string $column) => "AVG({$column}) as {$column}")
            ->implode(', '))
            ->first();

        return [
            'labels' => array_keys(self::CORE_VALUES),
            'data' => collect(self::CORE_VALUES)
                ->map(fn (string $column) => $this->roundNullable($row?->{$column}) ?? 0)
                ->values()
                ->all(),
        ];
    }

    public function departmentRows(?int $periodId, ?int $departmentId = null): Collection
    {
        $departments = Department::active()
            ->withCount(['employees as employee_count' => fn (Builder $query) => $query->active()])
            ->when($departmentId, fn (Builder $query) => $query->whereKey($departmentId))
            ->orderBy('name')
            ->get();

        $resultStats = AssessmentResult::query()
            ->join('employees', 'employees.id', '=', 'assessment_results.employee_id')
            ->when($periodId, fn ($query) => $query->where('assessment_results.assessment_period_id', $periodId))
            ->selectRaw('employees.department_id, COUNT(DISTINCT assessment_results.employee_id) as assessed_count, AVG(assessment_results.final_score) as average_score')
            ->groupBy('employees.department_id')
            ->get()
            ->keyBy('department_id');

        $period = $periodId ? AssessmentPeriod::find($periodId) : null;
        $belowCounts = AssessmentResult::query()
            ->join('employees', 'employees.id', '=', 'assessment_results.employee_id')
            ->when($periodId, fn ($query) => $query->where('assessment_results.assessment_period_id', $periodId))
            ->when($period, fn ($query) => $query->where('assessment_results.final_score', '<', $period->threshold_score))
            ->selectRaw('employees.department_id, COUNT(DISTINCT assessment_results.employee_id) as total')
            ->groupBy('employees.department_id')
            ->pluck('total', 'department_id');

        $assignmentStats = AssessmentAssignment::query()
            ->join('employees', 'employees.id', '=', 'assessment_assignments.assessee_employee_id')
            ->when($periodId, fn ($query) => $query->where('assessment_assignments.assessment_period_id', $periodId))
            ->selectRaw("employees.department_id, COUNT(*) as total, SUM(CASE WHEN assessment_assignments.status = 'submitted' THEN 1 ELSE 0 END) as submitted")
            ->groupBy('employees.department_id')
            ->get()
            ->keyBy('department_id');

        return $departments->map(function (Department $department) use ($resultStats, $belowCounts, $assignmentStats) {
            $results = $resultStats->get($department->id);
            $assignments = $assignmentStats->get($department->id);
            $totalAssignments = (int) ($assignments?->total ?? 0);
            $submittedAssignments = (int) ($assignments?->submitted ?? 0);

            return [
                'id' => $department->id,
                'name' => $department->name,
                'employee_count' => (int) $department->employee_count,
                'assessed_count' => (int) ($results?->assessed_count ?? 0),
                'average_score' => $this->roundNullable($results?->average_score),
                'below_threshold_count' => (int) ($belowCounts[$department->id] ?? 0),
                'completion_percentage' => $this->percentage($submittedAssignments, $totalAssignments),
            ];
        });
    }

    public function semesterTrend(?int $departmentId = null): array
    {
        $selects = collect(self::CORE_VALUES)
            ->map(fn (string $column) => "AVG(assessment_results.{$column}) as {$column}")
            ->implode(', ');

        $rows = AssessmentPeriod::query()
            ->leftJoin('assessment_results', 'assessment_results.assessment_period_id', '=', 'assessment_periods.id')
            ->leftJoin('employees', 'employees.id', '=', 'assessment_results.employee_id')
            ->when($departmentId, fn ($query) => $query->where(function ($query) use ($departmentId) {
                $query->where('employees.department_id', $departmentId)
                    ->orWhereNull('assessment_results.id');
            }))
            ->select([
                'assessment_periods.id',
                'assessment_periods.name',
                'assessment_periods.semester',
                'assessment_periods.year',
                'assessment_periods.start_date',
            ])
            ->selectRaw("AVG(assessment_results.final_score) as final_score, {$selects}")
            ->groupBy('assessment_periods.id', 'assessment_periods.name', 'assessment_periods.semester', 'assessment_periods.year', 'assessment_periods.start_date')
            ->orderBy('assessment_periods.start_date')
            ->get();

        return [
            'labels' => $rows->map(fn ($row) => "{$row->semester} {$row->year}")->all(),
            'final' => $rows->map(fn ($row) => $this->roundNullable($row->final_score))->all(),
            'core_values' => collect(self::CORE_VALUES)->map(fn (string $column, string $label) => [
                'label' => $label,
                'data' => $rows->map(fn ($row) => $this->roundNullable($row->{$column}))->all(),
            ])->values()->all(),
            'period_count' => $rows->count(),
            'result_period_count' => $rows->filter(fn ($row) => $row->final_score !== null)->count(),
        ];
    }

    public function weakestCoreValue(AssessmentResult $result): ?string
    {
        return collect(self::CORE_VALUES)
            ->mapWithKeys(fn (string $column, string $label) => [$label => $result->{$column}])
            ->filter(fn ($score) => $score !== null)
            ->sort()
            ->keys()
            ->first();
    }

    public function gapInterpretation(mixed $gap): array
    {
        $value = (float) $gap;

        return match (true) {
            $value > 0.50 => ['label' => 'Penilaian diri lebih tinggi', 'theme' => 'warning'],
            $value < -0.50 => ['label' => 'Penilaian diri lebih rendah', 'theme' => 'danger'],
            default => ['label' => 'Selaras', 'theme' => 'success'],
        };
    }

    public function percentage(int $part, int $total): float
    {
        return $total === 0 ? 0 : round(($part / $total) * 100, 1);
    }

    public function roundNullable(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }
}
