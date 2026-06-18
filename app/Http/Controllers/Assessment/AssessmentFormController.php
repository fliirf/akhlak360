<?php

namespace App\Http\Controllers\Assessment;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentResponse;
use App\Models\AssessmentResult;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AssessmentResultService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssessmentFormController extends Controller
{
    public const INDICATORS = [
        'Amanah' => [
            'Menepati janji dan komitmen kerja',
            'Bertanggung jawab atas hasil kerja',
            'Tidak menyalahgunakan wewenang',
        ],
        'Kompeten' => [
            'Meningkatkan kompetensi secara berkelanjutan',
            'Berbagi pengetahuan dengan tim',
            'Menyelesaikan pekerjaan dengan kualitas tinggi',
        ],
        'Harmonis' => [
            'Menghargai perbedaan',
            'Membangun kerja sama yang positif',
            'Menjaga komunikasi yang santun',
        ],
        'Loyal' => [
            'Mendukung kebijakan perusahaan',
            'Mengutamakan kepentingan perusahaan',
            'Menjaga reputasi BUMN',
        ],
        'Adaptif' => [
            'Terbuka terhadap perubahan',
            'Cepat merespons tantangan',
            'Inovatif dalam bekerja',
        ],
        'Kolaboratif' => [
            'Aktif bekerja lintas divisi',
            'Membangun sinergi',
            'Berbagi informasi secara terbuka',
        ],
    ];

    public function pending(Request $request): View
    {
        $employee = $this->employeeOrAbort($request);

        $assignments = AssessmentAssignment::query()
            ->with(['assessmentPeriod', 'assessee.department'])
            ->where('assessor_employee_id', $employee->id)
            ->pending()
            ->latest()
            ->paginate(10);

        return view('assessment.forms.pending', compact('assignments'));
    }

    public function redirectToPending(): RedirectResponse
    {
        return redirect()->route('assessment.pending.index');
    }

    public function results(Request $request): View
    {
        $employee = $this->employeeOrAbort($request);
        $validated = $request->validate([
            'period_id' => ['nullable', 'integer', 'exists:assessment_periods,id'],
        ]);
        $periods = AssessmentPeriod::orderByDesc('year')->orderByDesc('start_date')->get();
        $selectedPeriod = isset($validated['period_id'])
            ? (int) $validated['period_id']
            : ($periods->firstWhere('status', 'active') ?? $periods->first())?->id;
        $employeeIds = $request->user()->hasRole('supervisor')
            ? $employee->subordinates()->pluck('id')->push($employee->id)
            : collect([$employee->id]);

        $results = AssessmentResult::query()
            ->with(['assessmentPeriod', 'employee.department', 'employee.idpRecommendations' => fn ($query) => $query
                ->when($selectedPeriod, fn ($query) => $query->where('assessment_period_id', $selectedPeriod))])
            ->whereIn('employee_id', $employeeIds)
            ->when($selectedPeriod, fn (Builder $query) => $query->where('assessment_period_id', $selectedPeriod))
            ->join('employees', 'employees.id', '=', 'assessment_results.employee_id')
            ->orderBy('employees.name')
            ->select('assessment_results.*')
            ->paginate(15)
            ->withQueryString();

        return view('assessment.results.index', [
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'results' => $results,
            'isSupervisor' => $request->user()->hasRole('supervisor'),
        ]);
    }

    public function show(Request $request, AssessmentAssignment $assignment): View|RedirectResponse
    {
        $this->authorizeAssignment($request, $assignment);

        if ($assignment->status !== 'pending') {
            return redirect()
                ->route('assessment.pending.index')
                ->with('warning', 'This assessment has already been submitted.');
        }

        $assignment->load(['assessmentPeriod', 'assessor', 'assessee.department']);

        return view('assessment.forms.show', [
            'assignment' => $assignment,
            'indicators' => self::INDICATORS,
            'scale' => $this->scale(),
        ]);
    }

    public function submit(Request $request, AssessmentAssignment $assignment, AssessmentResultService $resultService): RedirectResponse
    {
        $this->authorizeAssignment($request, $assignment);

        if ($assignment->status !== 'pending' || $assignment->responses()->exists()) {
            return redirect()
                ->route('assessment.pending.index')
                ->with('warning', 'Duplicate submission prevented. This assessment has already been submitted.');
        }

        $validated = $request->validate($this->responseRules());

        DB::transaction(function () use ($request, $assignment, $validated, $resultService): void {
            foreach (self::INDICATORS as $coreValue => $indicators) {
                foreach ($indicators as $index => $indicator) {
                    AssessmentResponse::create([
                        'assessment_assignment_id' => $assignment->id,
                        'core_value' => $coreValue,
                        'indicator' => $indicator,
                        'score' => $validated['scores'][$coreValue][$index],
                    ]);
                }
            }

            $assignment->update([
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            User::role('admin_hr')->get()->each(function (User $admin) use ($assignment): void {
                AppNotification::create([
                    'user_id' => $admin->id,
                    'title' => 'Assessment Submitted',
                    'message' => "{$assignment->assessor->name} submitted {$assignment->assessor_type} assessment for {$assignment->assessee->name}.",
                    'type' => 'assessment_reminder',
                    'destination_url' => route('assessment-cycle.assign-assessors.index', [
                        'assessment_period_id' => $assignment->assessment_period_id,
                        'status' => 'submitted',
                    ], false),
                ]);
            });

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'submit',
                'module' => 'assessment_forms',
                'description' => "Submitted assessment assignment #{$assignment->id} at ".now()->toDateTimeString().'.',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $resultService->calculateForEmployeePeriod(
                $assignment->assessee_employee_id,
                $assignment->assessment_period_id,
                $request->user()?->id,
            );
        });

        return redirect()
            ->route('assessment.pending.index')
            ->with('success', 'Assessment submitted successfully.');
    }

    private function employeeOrAbort(Request $request)
    {
        $employee = $request->user()?->employee;

        abort_unless($employee, 403, 'Your user account is not linked to an employee profile.');

        return $employee;
    }

    private function authorizeAssignment(Request $request, AssessmentAssignment $assignment): void
    {
        $employee = $this->employeeOrAbort($request);

        abort_unless((int) $assignment->assessor_employee_id === (int) $employee->id, 403);
    }

    private function responseRules(): array
    {
        $rules = [];

        foreach (self::INDICATORS as $coreValue => $indicators) {
            foreach (array_keys($indicators) as $index) {
                $rules["scores.{$coreValue}.{$index}"] = ['required', 'integer', Rule::in([1, 2, 3, 4, 5])];
            }
        }

        return $rules;
    }

    private function scale(): array
    {
        return [
            1 => 'Sangat Tidak Sesuai',
            2 => 'Tidak Sesuai',
            3 => 'Cukup Sesuai',
            4 => 'Sesuai',
            5 => 'Sangat Sesuai',
        ];
    }
}
