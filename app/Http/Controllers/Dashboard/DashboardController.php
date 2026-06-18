<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function adminHr(): View
    {
        return view('dashboards.admin-hr', $this->dashboard->adminHrData());
    }

    public function supervisor(Request $request): View
    {
        return view('dashboards.supervisor', $this->dashboard->supervisorData($request->user()));
    }

    public function employee(Request $request): View
    {
        return view('dashboards.employee', $this->dashboard->employeeData($request->user()));
    }

    public function management(Request $request): View
    {
        $validated = $request->validate([
            'period_id' => ['nullable', 'integer', 'exists:assessment_periods,id'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'category' => ['nullable', Rule::in(DashboardService::RESULT_CATEGORIES)],
            'talent_category' => ['nullable', Rule::in(DashboardService::TALENT_CATEGORIES)],
        ]);

        return view('dashboards.management', $this->dashboard->managementData($validated));
    }

    public function itAdmin(): View
    {
        return view('dashboards.it-admin', $this->dashboard->itAdminData());
    }
}
