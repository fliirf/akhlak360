<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentResult;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CoreValueDashboardController extends Controller
{
    private const CORE_VALUE_COLUMNS = [
        'Amanah' => 'amanah_score',
        'Kompeten' => 'kompeten_score',
        'Harmonis' => 'harmonis_score',
        'Loyal' => 'loyal_score',
        'Adaptif' => 'adaptif_score',
        'Kolaboratif' => 'kolaboratif_score',
    ];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'period_id' => ['nullable', 'integer', Rule::exists('assessment_periods', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
        ]);
        $periods = AssessmentPeriod::orderByDesc('year')
            ->orderByDesc('start_date')
            ->get();
        $departments = Department::active()->orderBy('name')->get();

        $defaultPeriod = $periods->firstWhere('status', 'active') ?? $periods->first();
        $selectedPeriod = isset($validated['period_id']) ? (int) $validated['period_id'] : $defaultPeriod?->id;
        $selectedDepartment = isset($validated['department_id']) ? (int) $validated['department_id'] : null;

        $resultQuery = AssessmentResult::query()
            ->with(['employee.department', 'assessmentPeriod'])
            ->when($selectedPeriod, fn (Builder $query) => $query->where('assessment_period_id', $selectedPeriod))
            ->when($selectedDepartment, fn (Builder $query) => $query->whereHas(
                'employee',
                fn (Builder $employeeQuery) => $employeeQuery->where('department_id', $selectedDepartment)
            ))
            ->where(function (Builder $query) {
                foreach (self::CORE_VALUE_COLUMNS as $column) {
                    $query->orWhereNotNull($column);
                }
            });

        $coreValueAverages = $this->coreValueAverages(clone $resultQuery);
        $hasData = (clone $resultQuery)->exists();
        $ranking = $this->ranking($coreValueAverages);

        return view('analytics.core-value-dashboard', [
            'periods' => $periods,
            'departments' => $departments,
            'selectedPeriod' => $selectedPeriod,
            'selectedDepartment' => $selectedDepartment,
            'summary' => [
                'total' => (clone $resultQuery)->count(),
                'averageFinalScore' => $this->roundNullable((clone $resultQuery)->avg('final_score')),
                'strongest' => $hasData ? $ranking->first() : null,
                'weakest' => $hasData ? $ranking->last() : null,
            ],
            'coreValueChart' => [
                'labels' => array_keys(self::CORE_VALUE_COLUMNS),
                'data' => $ranking->sortBy(fn (array $item) => array_search($item['label'], array_keys(self::CORE_VALUE_COLUMNS), true))
                    ->pluck('average')
                    ->map(fn (?float $score) => $score ?? 0)
                    ->values()
                    ->all(),
            ],
            'ranking' => $ranking,
            'hasData' => $hasData,
        ]);
    }

    private function coreValueAverages(Builder $query): Collection
    {
        $row = $query->selectRaw(collect(self::CORE_VALUE_COLUMNS)
            ->map(fn (string $column) => "AVG({$column}) as {$column}")
            ->implode(', '))
            ->first();

        return collect(self::CORE_VALUE_COLUMNS)->mapWithKeys(fn (string $column, string $label) => [
            $label => $this->roundNullable($row?->{$column}),
        ]);
    }

    private function ranking(Collection $coreValueAverages): Collection
    {
        return $coreValueAverages
            ->map(fn (?float $average, string $label) => [
                'label' => $label,
                'average' => $average,
                'interpretation' => $this->interpretation($average),
            ])
            ->sortByDesc(fn (array $item) => $item['average'] ?? -1)
            ->values();
    }

    private function interpretation(?float $score): string
    {
        if ($score === null) {
            return '-';
        }

        return match (true) {
            $score >= 4.21 => 'Sangat Baik',
            $score >= 3.41 => 'Baik',
            $score >= 2.61 => 'Cukup',
            $score >= 1.81 => 'Kurang',
            default => 'Sangat Kurang',
        };
    }

    private function roundNullable(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }
}
