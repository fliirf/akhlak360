<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentResult;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\HrisSyncLog;
use App\Models\IdpRecommendation;
use App\Models\PeerApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const CORE_VALUE_COLUMNS = [
        'Amanah' => 'amanah_score',
        'Kompeten' => 'kompeten_score',
        'Harmonis' => 'harmonis_score',
        'Loyal' => 'loyal_score',
        'Adaptif' => 'adaptif_score',
        'Kolaboratif' => 'kolaboratif_score',
    ];

    public function adminHr(): View
    {
        $activePeriod = AssessmentPeriod::active()->latest('start_date')->first();
        $assignmentQuery = AssessmentAssignment::query()
            ->when(
                $activePeriod,
                fn (Builder $query) => $query->where('assessment_period_id', $activePeriod->id),
                fn (Builder $query) => $query->whereRaw('1 = 0')
            );
        $totalAssignments = (clone $assignmentQuery)->count();
        $submittedAssignments = (clone $assignmentQuery)->submitted()->count();
        $pendingAssignments = (clone $assignmentQuery)->pending()->count();
        $resultQuery = AssessmentResult::query()
            ->when(
                $activePeriod,
                fn (Builder $query) => $query->where('assessment_period_id', $activePeriod->id),
                fn (Builder $query) => $query->whereRaw('1 = 0')
            );

        return view('dashboards.admin-hr', [
            'activePeriod' => $activePeriod,
            'stats' => [
                'totalEmployees' => Employee::active()->count(),
                'totalAssignments' => $totalAssignments,
                'submittedAssignments' => $submittedAssignments,
                'pendingAssignments' => $pendingAssignments,
                'completionRate' => $this->percentage($submittedAssignments, $totalAssignments),
                'averageFinalScore' => $this->roundNullable((clone $resultQuery)->avg('final_score')),
                'belowThreshold' => $activePeriod ? AssessmentResult::belowThreshold($activePeriod)->count() : 0,
            ],
            'recentAuditLogs' => AuditLog::with('user')->latest()->limit(8)->get(),
            'coreValueChart' => $this->coreValueAverages((clone $resultQuery)),
            'completionChart' => [
                'labels' => ['Submitted', 'Pending'],
                'data' => [$submittedAssignments, $pendingAssignments],
            ],
            'hasAssignments' => $totalAssignments > 0,
            'hasResults' => (clone $resultQuery)->exists(),
        ]);
    }

    public function management(Request $request): View
    {
        $validated = $request->validate([
            'period_id' => ['nullable', 'integer', 'exists:assessment_periods,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);
        $periods = AssessmentPeriod::orderByDesc('year')->orderByDesc('start_date')->get();
        $departments = Department::active()->orderBy('name')->get();
        $selectedPeriod = isset($validated['period_id'])
            ? (int) $validated['period_id']
            : ($periods->firstWhere('status', 'active') ?? $periods->first())?->id;
        $selectedDepartment = isset($validated['department_id']) ? (int) $validated['department_id'] : null;

        $resultQuery = AssessmentResult::query()
            ->with(['employee.department', 'assessmentPeriod'])
            ->when($selectedPeriod, fn (Builder $query) => $query->where('assessment_period_id', $selectedPeriod))
            ->when($selectedDepartment, fn (Builder $query) => $query->whereHas(
                'employee',
                fn (Builder $employeeQuery) => $employeeQuery->where('department_id', $selectedDepartment)
            ));

        $period = $selectedPeriod ? AssessmentPeriod::find($selectedPeriod) : null;

        return view('dashboards.management', [
            'periods' => $periods,
            'departments' => $departments,
            'selectedPeriod' => $selectedPeriod,
            'selectedDepartment' => $selectedDepartment,
            'coreValueChart' => $this->coreValueAverages((clone $resultQuery)),
            'departmentChart' => $this->departmentDistribution($selectedPeriod, $selectedDepartment),
            'trendChart' => $this->semesterTrend($selectedDepartment),
            'gapSummary' => [
                'averageSelf' => $this->roundNullable((clone $resultQuery)->avg('self_score')),
                'averageOthers' => $this->roundNullable((clone $resultQuery)->avg('others_score')),
                'averageGap' => $this->roundNullable((clone $resultQuery)->avg('gap_score')),
            ],
            'talentMappingChart' => $this->talentMappingCounts((clone $resultQuery)),
            'belowThresholdEmployees' => $period
                ? (clone $resultQuery)->where('final_score', '<', $period->threshold_score)->get()
                : collect(),
            'summary' => [
                'companyAverage' => $this->roundNullable((clone $resultQuery)->avg('final_score')),
                'assessedEmployees' => (clone $resultQuery)->distinct('employee_id')->count('employee_id'),
                'belowThreshold' => $period
                    ? (clone $resultQuery)->where('final_score', '<', $period->threshold_score)->count()
                    : 0,
            ],
        ]);
    }

    public function supervisor(Request $request): View
    {
        $supervisor = $request->user()->employee;
        $activePeriod = AssessmentPeriod::active()->latest('start_date')->first();
        $teamIds = $supervisor ? $supervisor->subordinates()->pluck('id') : collect();
        $assignmentQuery = AssessmentAssignment::query()
            ->when(
                $activePeriod,
                fn (Builder $query) => $query->where('assessment_period_id', $activePeriod->id),
                fn (Builder $query) => $query->whereRaw('1 = 0')
            )
            ->whereIn('assessee_employee_id', $teamIds);
        $totalAssignments = (clone $assignmentQuery)->count();
        $submittedAssignments = (clone $assignmentQuery)->submitted()->count();
        $resultQuery = AssessmentResult::query()
            ->whereIn('employee_id', $teamIds)
            ->when(
                $activePeriod,
                fn (Builder $query) => $query->where('assessment_period_id', $activePeriod->id),
                fn (Builder $query) => $query->whereRaw('1 = 0')
            );

        return view('dashboards.supervisor', [
            'activePeriod' => $activePeriod,
            'teamMembers' => $supervisor ? $supervisor->subordinates()->with(['department', 'position'])->get() : collect(),
            'stats' => [
                'teamMembers' => $teamIds->count(),
                'completionRate' => $this->percentage($submittedAssignments, $totalAssignments),
                'pendingApprovals' => $supervisor ? PeerApproval::pending()->where('supervisor_employee_id', $supervisor->id)->count() : 0,
                'pendingAssessments' => $supervisor && $activePeriod ? AssessmentAssignment::pending()->where('assessment_period_id', $activePeriod->id)->where('assessor_employee_id', $supervisor->id)->count() : 0,
                'submittedAssessments' => $supervisor && $activePeriod ? AssessmentAssignment::submitted()->where('assessment_period_id', $activePeriod->id)->where('assessor_employee_id', $supervisor->id)->count() : 0,
                'teamAverageScore' => $this->roundNullable((clone $resultQuery)->avg('final_score')),
                'belowThreshold' => $activePeriod ? (clone $resultQuery)->where('final_score', '<', $activePeriod->threshold_score)->count() : 0,
            ],
            'coreValueChart' => $this->coreValueAverages((clone $resultQuery)),
            'recentTeamResults' => (clone $resultQuery)
                ->with(['employee.department'])
                ->latest('updated_at')
                ->limit(8)
                ->get(),
        ]);
    }

    public function employee(Request $request): View
    {
        $employee = $request->user()->employee;
        $activePeriod = AssessmentPeriod::active()->latest('start_date')->first();
        $result = $employee && $activePeriod
            ? AssessmentResult::with('assessmentPeriod')
                ->where('employee_id', $employee->id)
                ->where('assessment_period_id', $activePeriod->id)
                ->latest()
                ->first()
            : null;
        $idp = $employee && $activePeriod
            ? IdpRecommendation::where('employee_id', $employee->id)
                ->where('assessment_period_id', $activePeriod->id)
                ->latest()
                ->first()
            : null;

        return view('dashboards.employee', [
            'activePeriod' => $activePeriod,
            'employee' => $employee,
            'stats' => [
                'pendingAssessments' => $employee && $activePeriod ? AssessmentAssignment::pending()->where('assessment_period_id', $activePeriod->id)->where('assessor_employee_id', $employee->id)->count() : 0,
                'completedAssessments' => $employee && $activePeriod ? AssessmentAssignment::submitted()->where('assessment_period_id', $activePeriod->id)->where('assessor_employee_id', $employee->id)->count() : 0,
            ],
            'result' => $result,
            'idp' => $idp,
            'personalCoreChart' => $result ? [
                'labels' => array_keys(self::CORE_VALUE_COLUMNS),
                'data' => collect(self::CORE_VALUE_COLUMNS)->map(fn (string $column) => (float) $result->{$column})->values()->all(),
            ] : ['labels' => [], 'data' => []],
            'gapChart' => $result ? [
                'labels' => ['Self', 'Others'],
                'data' => [(float) $result->self_score, (float) $result->others_score],
            ] : ['labels' => [], 'data' => []],
        ]);
    }

    public function itAdmin(): View
    {
        $latestSync = HrisSyncLog::with('syncedBy')->latest()->first();

        return view('dashboards.it-admin', [
            'stats' => [
                'users' => User::count(),
                'employees' => Employee::count(),
                'departments' => Department::count(),
                'auditLogs' => AuditLog::count(),
                'hrisSyncs' => HrisSyncLog::count(),
                'failedHrisSyncs' => HrisSyncLog::failed()->count(),
                'successfulHrisSyncs' => HrisSyncLog::successful()->count(),
                'notificationActivity' => AppNotification::count(),
                'reminderActivity' => AppNotification::type('assessment_reminder')->count(),
                'failedJobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
                'queuedJobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
            ],
            'hrisSyncLogs' => HrisSyncLog::with('syncedBy')->latest()->limit(8)->get(),
            'auditLogs' => AuditLog::with('user')->latest()->limit(10)->get(),
            'latestSync' => $latestSync,
            'notificationLogs' => AppNotification::with('user')->latest()->limit(8)->get(),
        ]);
    }

    private function coreValueAverages(Builder $query): array
    {
        $row = $query->selectRaw(collect(self::CORE_VALUE_COLUMNS)
            ->map(fn (string $column) => "AVG({$column}) as {$column}")
            ->implode(', '))
            ->first();

        return [
            'labels' => array_keys(self::CORE_VALUE_COLUMNS),
            'data' => collect(self::CORE_VALUE_COLUMNS)
                ->map(fn (string $column) => $this->roundNullable($row?->{$column}) ?? 0)
                ->values()
                ->all(),
        ];
    }

    private function departmentDistribution(?int $periodId, ?int $departmentId): array
    {
        $rows = Department::query()
            ->select('departments.name')
            ->selectRaw('AVG(assessment_results.final_score) as average_score')
            ->join('employees', 'employees.department_id', '=', 'departments.id')
            ->join('assessment_results', 'assessment_results.employee_id', '=', 'employees.id')
            ->when($periodId, fn ($query) => $query->where('assessment_results.assessment_period_id', $periodId))
            ->when($departmentId, fn ($query) => $query->where('departments.id', $departmentId))
            ->groupBy('departments.id', 'departments.name')
            ->orderBy('departments.name')
            ->get();

        return [
            'labels' => $rows->pluck('name')->all(),
            'data' => $rows->pluck('average_score')->map(fn ($score) => $this->roundNullable($score) ?? 0)->all(),
        ];
    }

    private function semesterTrend(?int $departmentId): array
    {
        $rows = AssessmentResult::query()
            ->with('assessmentPeriod')
            ->when($departmentId, fn (Builder $query) => $query->whereHas(
                'employee',
                fn (Builder $employeeQuery) => $employeeQuery->where('department_id', $departmentId)
            ))
            ->get()
            ->groupBy('assessment_period_id')
            ->map(function (Collection $results) {
                $period = $results->first()->assessmentPeriod;

                return [
                    'label' => $period ? "{$period->semester} {$period->year}" : 'Unknown',
                    'year' => $period?->year ?? 0,
                    'start_date' => $period?->start_date,
                    'average_score' => $this->roundNullable($results->avg('final_score')) ?? 0,
                ];
            })
            ->sortBy([['year', 'asc'], ['start_date', 'asc']])
            ->values();

        return [
            'labels' => $rows->pluck('label')->all(),
            'data' => $rows->pluck('average_score')->all(),
        ];
    }

    private function talentMappingCounts(Builder $query): array
    {
        $rows = $query
            ->select('talent_mapping_category', DB::raw('COUNT(*) as total'))
            ->whereNotNull('talent_mapping_category')
            ->groupBy('talent_mapping_category')
            ->orderBy('talent_mapping_category')
            ->get();

        return [
            'labels' => $rows->pluck('talent_mapping_category')->all(),
            'data' => $rows->pluck('total')->all(),
        ];
    }

    private function percentage(int $part, int $total): float
    {
        return $total === 0 ? 0 : round(($part / $total) * 100, 1);
    }

    private function roundNullable(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }
}
