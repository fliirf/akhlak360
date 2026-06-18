<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\IdpRecommendation;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BelowThresholdController extends Controller
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

        $results = $period
            ? $this->analytics->resultQuery($selectedPeriod, $selectedDepartment)
                ->where('final_score', '<', $period->threshold_score)
                ->orderBy('final_score')
                ->paginate(15)
                ->withQueryString()
            : collect();

        $idpStatuses = $period && $results->isNotEmpty()
            ? IdpRecommendation::where('assessment_period_id', $period->id)
                ->whereIn('employee_id', $results->pluck('employee_id'))
                ->pluck('status', 'employee_id')
            : collect();

        $results->each(function ($result) use ($idpStatuses) {
            $result->setAttribute('weakest_core_value_label', $this->analytics->weakestCoreValue($result));
            $result->setAttribute('idp_status_label', $idpStatuses[$result->employee_id] ?? null);
        });

        return view('analytics.below-threshold', [
            'periods' => $periods,
            'departments' => $this->analytics->departments(),
            'selectedPeriod' => $selectedPeriod,
            'selectedDepartment' => $selectedDepartment,
            'period' => $period,
            'results' => $results,
        ]);
    }
}
