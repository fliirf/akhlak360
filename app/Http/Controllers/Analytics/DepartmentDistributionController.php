<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentDistributionController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'period_id' => ['nullable', 'integer', Rule::exists('assessment_periods', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
        ]);
        $periods = $this->analytics->periods();
        $selectedPeriod = isset($validated['period_id']) ? (int) $validated['period_id'] : $this->analytics->defaultPeriodId($periods);
        $selectedDepartment = isset($validated['department_id']) ? (int) $validated['department_id'] : null;
        $period = $selectedPeriod ? AssessmentPeriod::find($selectedPeriod) : null;
        $rows = $this->analytics->departmentRows($selectedPeriod, $selectedDepartment);

        return view('analytics.department-distribution', [
            'periods' => $periods,
            'departments' => $this->analytics->departments(),
            'selectedPeriod' => $selectedPeriod,
            'selectedDepartment' => $selectedDepartment,
            'period' => $period,
            'rows' => $rows,
            'summary' => [
                'assessed' => $rows->sum('assessed_count'),
                'employees' => $rows->sum('employee_count'),
                'average' => $this->analytics->roundNullable($rows->whereNotNull('average_score')->avg('average_score')),
                'below' => $rows->sum('below_threshold_count'),
            ],
            'averageChart' => [
                'labels' => $rows->pluck('name')->all(),
                'data' => $rows->pluck('average_score')->all(),
            ],
            'distributionChart' => [
                'labels' => $rows->pluck('name')->all(),
                'assessed' => $rows->pluck('assessed_count')->all(),
                'unassessed' => $rows->map(fn (array $row) => max(0, $row['employee_count'] - $row['assessed_count']))->all(),
            ],
        ]);
    }
}
