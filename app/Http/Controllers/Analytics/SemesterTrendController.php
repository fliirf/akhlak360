<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SemesterTrendController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
        ]);
        $selectedDepartment = isset($validated['department_id']) ? (int) $validated['department_id'] : null;
        $trend = $this->analytics->semesterTrend($selectedDepartment);

        return view('analytics.semester-trend', [
            'departments' => $this->analytics->departments(),
            'selectedDepartment' => $selectedDepartment,
            'trend' => $trend,
        ]);
    }
}
