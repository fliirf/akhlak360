<?php

namespace App\Http\Controllers\AuditCompliance;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentPeriod;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ComplianceMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'period_id' => ['nullable', 'integer', Rule::exists('assessment_periods', 'id')],
        ]);
        $periods = AssessmentPeriod::orderByDesc('year')->orderByDesc('start_date')->get();
        $selectedPeriod = isset($validated['period_id'])
            ? (int) $validated['period_id']
            : ($periods->firstWhere('status', 'active') ?? $periods->first())?->id;
        $period = $selectedPeriod ? AssessmentPeriod::find($selectedPeriod) : null;

        $assignmentQuery = AssessmentAssignment::query()
            ->with(['assessmentPeriod', 'assessor.user', 'assessee.department'])
            ->when(
                $selectedPeriod,
                fn (Builder $query) => $query->where('assessment_period_id', $selectedPeriod),
                fn (Builder $query) => $query->whereRaw('1 = 0')
            );

        $total = (clone $assignmentQuery)->count();
        $submitted = (clone $assignmentQuery)->submitted()->count();
        $pending = (clone $assignmentQuery)->pending()->count();
        $overdueQuery = (clone $assignmentQuery)
            ->pending()
            ->whereHas('assessmentPeriod', fn (Builder $query) => $query->whereDate('end_date', '<', today()));
        $overdue = (clone $overdueQuery)->count();

        return view('audit-compliance.compliance-monitoring', [
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'period' => $period,
            'stats' => [
                'total' => $total,
                'submitted' => $submitted,
                'pending' => $pending,
                'overdue' => $overdue,
                'completionRate' => $total === 0 ? 0 : round(($submitted / $total) * 100, 1),
            ],
            'pendingAssignments' => (clone $assignmentQuery)->pending()->latest()->paginate(10, ['*'], 'pending_page')->withQueryString(),
            'overdueAssignments' => $overdueQuery
                ->latest()
                ->paginate(10, ['*'], 'overdue_page')
                ->withQueryString(),
        ]);
    }

    public function sendReminders(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('it_admin'), 403);

        Artisan::call('assessment:send-reminders');

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'generate_reminders',
            'module' => 'compliance_monitoring',
            'description' => trim(Artisan::output()) ?: 'Generated assessment reminders from compliance monitoring.',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', trim(Artisan::output()) ?: 'Assessment reminders generated.');
    }
}
