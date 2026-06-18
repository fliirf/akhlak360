<?php

namespace App\Services;

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
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    public const ASSESSOR_TYPES = ['supervisor', 'peer', 'subordinate', 'self'];

    public const TALENT_CATEGORIES = [
        'High Potential',
        'Solid Contributor',
        'Core Contributor',
        'Need Development',
    ];

    public const RESULT_CATEGORIES = [
        'Perlu Pengembangan',
        'Cukup',
        'Baik',
        'Sangat Baik',
    ];

    public function __construct(private readonly AnalyticsService $analytics) {}

    public function adminHrData(): array
    {
        $activePeriod = AssessmentPeriod::active()->with('weights')->latest('start_date')->first();
        $activeAssignments = $this->assignmentsForPeriod($activePeriod?->id);
        $allOverdue = $this->overdueAssignments();
        $resultQuery = $this->analytics->resultQuery($activePeriod?->id);
        $total = (clone $activeAssignments)->count();
        $submitted = (clone $activeAssignments)->submitted()->count();
        $pending = (clone $activeAssignments)->pending()->count();

        $attention = $activePeriod
            ? (clone $resultQuery)
                ->with(['employee.department', 'employee.idpRecommendations' => fn ($query) => $query
                    ->where('assessment_period_id', $activePeriod->id)])
                ->where('final_score', '<', $activePeriod->threshold_score)
                ->orderBy('final_score')
                ->limit(10)
                ->get()
            : collect();

        return [
            'activePeriod' => $activePeriod,
            'stats' => [
                'activeEmployees' => Employee::active()->count(),
                'totalAssignments' => $total,
                'submittedAssignments' => $submitted,
                'pendingAssignments' => $pending,
                'overdueAssignments' => (clone $allOverdue)->count(),
                'completionRate' => $this->analytics->percentage($submitted, $total),
                'belowThreshold' => $activePeriod
                    ? (clone $resultQuery)->where('final_score', '<', $activePeriod->threshold_score)->count()
                    : 0,
            ],
            'assessorProgress' => $this->assessorProgress($activeAssignments),
            'departmentRows' => $this->analytics->departmentRows($activePeriod?->id),
            'attentionResults' => $attention,
            'recentSubmissions' => AssessmentAssignment::with(['assessmentPeriod', 'assessee.department'])
                ->submitted()
                ->latest('submitted_at')
                ->limit(8)
                ->get(),
            'recentAuditLogs' => AuditLog::with('user')->latest()->limit(8)->get(),
            'recentHrisSyncs' => HrisSyncLog::with('syncedBy')->latest()->limit(5)->get(),
            'alerts' => [
                'noActivePeriod' => $activePeriod === null,
                'noAssignments' => $total === 0,
                'overdue' => (clone $allOverdue)->count(),
                'failedHris' => HrisSyncLog::failed()->count(),
                'openIdp' => IdpRecommendation::whereIn('status', ['draft', 'approved', 'in_progress'])->count(),
            ],
        ];
    }

    public function supervisorData(User $user): array
    {
        $supervisor = $user->employee;
        $activePeriod = AssessmentPeriod::active()->latest('start_date')->first();
        $teamMembers = $supervisor
            ? $supervisor->subordinates()->with(['department', 'position'])->orderBy('name')->get()
            : collect();
        $teamIds = $teamMembers->pluck('id');
        $teamAssignments = $this->assignmentsForPeriod($activePeriod?->id)
            ->whereIn('assessee_employee_id', $teamIds);
        $myAssignments = $this->assignmentsForPeriod($activePeriod?->id)
            ->where('assessor_employee_id', $supervisor?->id ?? 0);
        $teamStatus = $teamMembers->map(function (Employee $member) use ($activePeriod) {
            $query = $this->assignmentsForPeriod($activePeriod?->id)
                ->where('assessee_employee_id', $member->id);
            $total = (clone $query)->count();
            $submitted = (clone $query)->submitted()->count();

            return [
                'employee' => $member,
                'total' => $total,
                'submitted' => $submitted,
                'pending' => (clone $query)->pending()->count(),
                'completion' => $this->analytics->percentage($submitted, $total),
                'deadline' => $activePeriod?->end_date,
            ];
        });
        $teamResults = AssessmentResult::query()
            ->whereIn('employee_id', $teamIds)
            ->when($activePeriod, fn (Builder $query) => $query->where('assessment_period_id', $activePeriod->id));

        return [
            'activePeriod' => $activePeriod,
            'supervisor' => $supervisor,
            'teamMembers' => $teamMembers,
            'teamStatus' => $teamStatus,
            'stats' => [
                'teamMembers' => $teamIds->count(),
                'teamCompletion' => $this->analytics->percentage(
                    (clone $teamAssignments)->submitted()->count(),
                    (clone $teamAssignments)->count()
                ),
                'pendingApprovals' => $supervisor
                    ? PeerApproval::pending()->where('supervisor_employee_id', $supervisor->id)->count()
                    : 0,
                'myPending' => (clone $myAssignments)->actionable()->count(),
                'myOverdue' => $this->applyOverdue((clone $myAssignments))->count(),
                'developmentAttention' => $activePeriod
                    ? (clone $teamResults)->where('final_score', '<', $activePeriod->threshold_score)->count()
                    : 0,
            ],
            'assessorTypeAggregates' => $this->scoreAggregatesByAssessorType($activePeriod?->id, $teamIds),
            'developmentSummary' => $this->developmentSummary($teamIds, $activePeriod?->id),
        ];
    }

    public function employeeData(User $user): array
    {
        $employee = $user->employee;
        $activePeriod = AssessmentPeriod::active()->latest('start_date')->first();
        $taskQuery = $this->assignmentsForPeriod($activePeriod?->id)
            ->where('assessor_employee_id', $employee?->id ?? 0);
        $result = $employee && $activePeriod
            ? AssessmentResult::with('assessmentPeriod')
                ->where('employee_id', $employee->id)
                ->where('assessment_period_id', $activePeriod->id)
                ->first()
            : null;
        $history = $employee
            ? AssessmentResult::with('assessmentPeriod')
                ->where('employee_id', $employee->id)
                ->latest('assessment_period_id')
                ->get()
            : collect();

        return [
            'activePeriod' => $activePeriod,
            'employee' => $employee,
            'taskStats' => [
                'pending' => (clone $taskQuery)->actionable()->count(),
                'overdue' => $this->applyOverdue((clone $taskQuery))->count(),
                'completed' => (clone $taskQuery)->submitted()->count(),
                'nearestDeadline' => (clone $taskQuery)->actionable()
                    ->with('assessmentPeriod')
                    ->get()
                    ->pluck('assessmentPeriod.end_date')
                    ->filter()
                    ->sort()
                    ->first(),
            ],
            'pendingTasks' => (clone $taskQuery)
                ->with(['assessmentPeriod', 'assessee.department'])
                ->actionable()
                ->latest()
                ->limit(8)
                ->get(),
            'submissionHistory' => AssessmentAssignment::with(['assessmentPeriod', 'assessee.department'])
                ->where('assessor_employee_id', $employee?->id ?? 0)
                ->submitted()
                ->latest('submitted_at')
                ->limit(8)
                ->get(),
            'result' => $result,
            'resultHistory' => $history,
            'resultTrend' => [
                'labels' => $history->sortBy('assessmentPeriod.start_date')
                    ->map(fn ($item) => $item->assessmentPeriod?->name ?? '-')
                    ->values()
                    ->all(),
                'data' => $history->sortBy('assessmentPeriod.start_date')
                    ->pluck('final_score')
                    ->map(fn ($score) => (float) $score)
                    ->values()
                    ->all(),
            ],
            'assessorTypeAggregates' => $employee
                ? $this->scoreAggregatesByAssessorType($activePeriod?->id, collect([$employee->id]))
                : collect(),
            'idp' => $employee && $activePeriod
                ? IdpRecommendation::where('employee_id', $employee->id)
                    ->where('assessment_period_id', $activePeriod->id)
                    ->latest()
                    ->first()
                : null,
            'personalCoreChart' => $result ? [
                'labels' => array_keys(AnalyticsService::CORE_VALUES),
                'data' => collect(AnalyticsService::CORE_VALUES)
                    ->map(fn (string $column) => (float) $result->{$column})
                    ->values()
                    ->all(),
            ] : ['labels' => [], 'data' => []],
        ];
    }

    public function managementData(array $filters): array
    {
        $periods = $this->analytics->periods();
        $departments = $this->analytics->departments();
        $periodId = $filters['period_id'] ?? $this->analytics->defaultPeriodId($periods);
        $departmentId = $filters['department_id'] ?? null;
        $query = $this->analytics->resultQuery($periodId, $departmentId)
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['talent_category'] ?? null, fn (Builder $query, string $category) => $query->where('talent_mapping_category', $category));
        $period = $periodId ? AssessmentPeriod::find($periodId) : null;
        $results = (clone $query)->get();
        $departmentRows = $this->managementDepartmentRows($results, $periodId, $departmentId, $period);
        $idpQuery = IdpRecommendation::query()
            ->when($periodId, fn (Builder $query) => $query->where('assessment_period_id', $periodId))
            ->when($departmentId, fn (Builder $query) => $query->whereHas(
                'employee',
                fn (Builder $employeeQuery) => $employeeQuery->where('department_id', $departmentId)
            ));

        return [
            'periods' => $periods,
            'departments' => $departments,
            'categories' => self::RESULT_CATEGORIES,
            'talentCategories' => self::TALENT_CATEGORIES,
            'filters' => [
                'period_id' => $periodId,
                'department_id' => $departmentId,
                'category' => $filters['category'] ?? null,
                'talent_category' => $filters['talent_category'] ?? null,
            ],
            'summary' => [
                'assessedEmployees' => $results->pluck('employee_id')->unique()->count(),
                'averageScore' => $this->analytics->roundNullable($results->avg('final_score')),
                'completionRate' => $this->completionRate($periodId, $departmentId),
                'belowThreshold' => $period
                    ? $results->where('final_score', '<', (float) $period->threshold_score)->count()
                    : 0,
                'highPotential' => $results->where('talent_mapping_category', 'High Potential')->count(),
                'activeIdp' => (clone $idpQuery)->whereIn('status', ['approved', 'in_progress'])->count(),
            ],
            'coreValueChart' => $this->analytics->coreValueAverages(clone $query),
            'trendChart' => $this->analytics->semesterTrend($departmentId),
            'gapDistribution' => [
                'labels' => ['Self Higher', 'Aligned', 'Self Lower'],
                'data' => [
                    $results->where('gap_score', '>', .5)->count(),
                    $results->filter(fn ($result) => (float) $result->gap_score <= .5 && (float) $result->gap_score >= -.5)->count(),
                    $results->where('gap_score', '<', -.5)->count(),
                ],
            ],
            'talentChart' => [
                'labels' => self::TALENT_CATEGORIES,
                'data' => collect(self::TALENT_CATEGORIES)
                    ->map(fn (string $category) => $results->where('talent_mapping_category', $category)->count())
                    ->all(),
            ],
            'departmentRows' => $departmentRows,
            'idpStatus' => (clone $idpQuery)->get()->groupBy('status')->map->count(),
        ];
    }

    public function itAdminData(): array
    {
        $latestReminder = AppNotification::with('user')
            ->type('assessment_reminder')
            ->latest()
            ->first();
        $latestReminderRun = AuditLog::query()
            ->where('module', 'notifications')
            ->where('action', 'send_reminders')
            ->latest()
            ->first();

        return [
            'stats' => [
                'activeUsers30Days' => User::whereNotNull('last_login_at')
                    ->where('last_login_at', '>=', now()->subDays(30))
                    ->count(),
                'auditToday' => AuditLog::whereDate('created_at', today())->count(),
                'successfulHrisSyncs' => HrisSyncLog::successful()->count(),
                'failedHrisSyncs' => HrisSyncLog::failed()->count(),
                'generatedExports' => ReportExport::generated()->count(),
                'failedExports' => ReportExport::failed()->count(),
                'recordedReminderActivity' => AuditLog::where('module', 'notifications')
                    ->where('action', 'send_reminders')
                    ->count(),
                'queuedJobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
                'failedJobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            ],
            'reminderConfiguration' => [
                'schedule' => 'Daily at 08:00',
                'intervalDays' => (int) config('akhlak360.reminder_interval_days', 3),
                'emailEnabled' => (bool) config('akhlak360.email_notifications_enabled', true),
                'inAppEnabled' => (bool) config('akhlak360.in_app_notifications_enabled', true),
                'command' => 'assessment:send-reminders',
            ],
            'latestGeneratedReminder' => $latestReminder,
            'latestReminderActivity' => $latestReminderRun,
            'hrisSyncLogs' => HrisSyncLog::with('syncedBy')->latest()->limit(8)->get(),
            'auditLogs' => AuditLog::with('user')->latest()->limit(10)->get(),
            'exportLogs' => ReportExport::with(['user', 'assessmentPeriod'])->latest()->limit(8)->get(),
            'reminderLogs' => AuditLog::with('user')
                ->where(function (Builder $query) {
                    $query->where('module', 'notifications')->where('action', 'send_reminders')
                        ->orWhere(function (Builder $query) {
                            $query->where('module', 'compliance_monitoring')->where('action', 'generate_reminders');
                        });
                })
                ->latest()
                ->limit(8)
                ->get(),
            'runtime' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'environment' => app()->environment(),
                'queue' => config('queue.default'),
                'database' => config('database.default'),
                'mail' => config('mail.default'),
            ],
            'complianceChecklist' => [
                ['label' => 'Audit logging records available', 'ok' => AuditLog::exists()],
                ['label' => 'Reminder interval configured', 'ok' => (int) config('akhlak360.reminder_interval_days', 0) > 0],
                ['label' => 'HRIS activity recorded', 'ok' => HrisSyncLog::exists()],
                ['label' => 'Export activity recorded', 'ok' => ReportExport::exists()],
                ['label' => 'Failed queue jobs clear', 'ok' => ! Schema::hasTable('failed_jobs') || DB::table('failed_jobs')->count() === 0],
            ],
        ];
    }

    public function overdueAssignments(): Builder
    {
        return $this->applyOverdue(AssessmentAssignment::query());
    }

    private function applyOverdue(Builder $query): Builder
    {
        return $query->pending()->whereHas(
            'assessmentPeriod',
            fn (Builder $periodQuery) => $periodQuery->whereDate('end_date', '<', today())
        );
    }

    private function assignmentsForPeriod(?int $periodId): Builder
    {
        return AssessmentAssignment::query()->when(
            $periodId,
            fn (Builder $query) => $query->where('assessment_period_id', $periodId),
            fn (Builder $query) => $query->whereRaw('1 = 0')
        );
    }

    private function assessorProgress(Builder $baseQuery): Collection
    {
        return collect(self::ASSESSOR_TYPES)->map(function (string $type) use ($baseQuery) {
            $query = (clone $baseQuery)->where('assessor_type', $type);
            $total = (clone $query)->count();
            $submitted = (clone $query)->submitted()->count();

            return [
                'type' => $type,
                'total' => $total,
                'submitted' => $submitted,
                'pending' => (clone $query)->pending()->count(),
                'completion' => $this->analytics->percentage($submitted, $total),
            ];
        });
    }

    private function scoreAggregatesByAssessorType(?int $periodId, Collection $employeeIds): Collection
    {
        if (! $periodId || $employeeIds->isEmpty()) {
            return collect();
        }

        $rows = AssessmentAssignment::query()
            ->join('assessment_responses', 'assessment_responses.assessment_assignment_id', '=', 'assessment_assignments.id')
            ->where('assessment_assignments.assessment_period_id', $periodId)
            ->where('assessment_assignments.status', 'submitted')
            ->whereIn('assessment_assignments.assessee_employee_id', $employeeIds)
            ->selectRaw('assessment_assignments.assessor_type, AVG(assessment_responses.score) as average_score, COUNT(DISTINCT assessment_assignments.id) as assignment_count')
            ->groupBy('assessment_assignments.assessor_type')
            ->get()
            ->keyBy('assessor_type');

        return collect(self::ASSESSOR_TYPES)->map(function (string $type) use ($rows) {
            $row = $rows->get($type);

            return [
                'type' => $type,
                'average' => $this->analytics->roundNullable($row?->average_score),
                'assignments' => (int) ($row?->assignment_count ?? 0),
            ];
        });
    }

    private function developmentSummary(Collection $employeeIds, ?int $periodId): Collection
    {
        if ($employeeIds->isEmpty() || ! $periodId) {
            return collect();
        }

        return IdpRecommendation::query()
            ->where('assessment_period_id', $periodId)
            ->whereIn('employee_id', $employeeIds)
            ->selectRaw('weakest_core_value, status, COUNT(*) as total')
            ->groupBy('weakest_core_value', 'status')
            ->orderBy('weakest_core_value')
            ->get();
    }

    private function managementDepartmentRows(Collection $results, ?int $periodId, ?int $departmentId, ?AssessmentPeriod $period): Collection
    {
        $departments = Department::active()
            ->when($departmentId, fn (Builder $query) => $query->whereKey($departmentId))
            ->orderBy('name')
            ->get();
        $grouped = $results->groupBy(fn ($result) => $result->employee?->department_id);

        return $departments->map(function (Department $department) use ($grouped, $periodId, $period) {
            $departmentResults = $grouped->get($department->id, collect());
            $assignments = AssessmentAssignment::query()
                ->join('employees', 'employees.id', '=', 'assessment_assignments.assessee_employee_id')
                ->where('employees.department_id', $department->id)
                ->when($periodId, fn ($query) => $query->where('assessment_assignments.assessment_period_id', $periodId));
            $total = (clone $assignments)->count();
            $submitted = (clone $assignments)->where('assessment_assignments.status', 'submitted')->count();

            return [
                'department' => $department,
                'assessed' => $departmentResults->pluck('employee_id')->unique()->count(),
                'average' => $this->analytics->roundNullable($departmentResults->avg('final_score')),
                'belowThreshold' => $period
                    ? $departmentResults->where('final_score', '<', (float) $period->threshold_score)->count()
                    : 0,
                'completion' => $this->analytics->percentage($submitted, $total),
                'highPotential' => $departmentResults->where('talent_mapping_category', 'High Potential')->count(),
            ];
        });
    }

    private function completionRate(?int $periodId, ?int $departmentId): float
    {
        $query = AssessmentAssignment::query()
            ->when($periodId, fn (Builder $query) => $query->where('assessment_period_id', $periodId))
            ->when($departmentId, fn (Builder $query) => $query->whereHas(
                'assessee',
                fn (Builder $employeeQuery) => $employeeQuery->where('department_id', $departmentId)
            ));
        $total = (clone $query)->count();

        return $this->analytics->percentage((clone $query)->submitted()->count(), $total);
    }
}
