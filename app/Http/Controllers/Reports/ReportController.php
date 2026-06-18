<?php

namespace App\Http\Controllers\Reports;

use App\Exports\AssessmentResultsExport;
use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentResult;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\ReportExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    private const CATEGORIES = [
        'Perlu Pengembangan',
        'Cukup',
        'Baik',
        'Sangat Baik',
    ];

    private const HEADERS = [
        'period',
        'employee_number',
        'employee_name',
        'department',
        'position',
        'amanah_score',
        'kompeten_score',
        'harmonis_score',
        'loyal_score',
        'adaptif_score',
        'kolaboratif_score',
        'self_score',
        'others_score',
        'gap_score',
        'final_score',
        'category',
        'talent_mapping_category',
        'weakest_core_value',
        'idp_recommendation',
    ];

    public function index(Request $request): View
    {
        $this->validateFilters($request);
        $query = $this->filteredQuery($request);

        return view('reports.index', [
            'periods' => AssessmentPeriod::orderByDesc('year')->orderByDesc('start_date')->get(),
            'departments' => Department::active()->orderBy('name')->get(),
            'categories' => self::CATEGORIES,
            'results' => (clone $query)
                ->paginate(15)
                ->withQueryString(),
            'summary' => [
                'records' => (clone $query)->count(),
                'averageScore' => round((float) ((clone $query)->avg('final_score') ?? 0), 2),
                'belowThreshold' => (clone $query)->whereRaw(
                    'assessment_results.final_score < (select threshold_score from assessment_periods where assessment_periods.id = assessment_results.assessment_period_id)'
                )->count(),
            ],
            'excelAvailable' => class_exists('Maatwebsite\\Excel\\Facades\\Excel'),
            'pdfAvailable' => class_exists('Barryvdh\\DomPDF\\Facade\\Pdf'),
        ]);
    }

    public function history(Request $request): View
    {
        $validated = $request->validate([
            'report_type' => ['nullable', Rule::in(['csv', 'excel', 'pdf'])],
            'status' => ['nullable', Rule::in(['generated', 'failed'])],
            'period_id' => ['nullable', 'integer', 'exists:assessment_periods,id'],
        ]);
        $query = ReportExport::query()
            ->with(['user', 'assessmentPeriod'])
            ->when($validated['report_type'] ?? null, fn ($query, $type) => $query->reportType($type))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['period_id'] ?? null, fn ($query, $periodId) => $query->where('assessment_period_id', $periodId));

        return view('reports.history', [
            'exports' => (clone $query)
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'periods' => AssessmentPeriod::orderByDesc('year')->orderByDesc('start_date')->get(),
            'summary' => [
                'total' => (clone $query)->count(),
                'generated' => (clone $query)->generated()->count(),
                'failed' => (clone $query)->failed()->count(),
            ],
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $this->validateFilters($request);
        $filename = 'akhlak360-report-'.now()->format('Ymd-His').'.csv';
        $this->recordExport($request, 'csv', $filename, 'generated');

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::HEADERS);

            $this->filteredQuery($request)
                ->chunk(100, function (Collection $results) use ($handle): void {
                    foreach ($results as $result) {
                        fputcsv($handle, $this->row($result));
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function excel(Request $request): BinaryFileResponse|RedirectResponse
    {
        $this->validateFilters($request);
        $filename = 'akhlak360-report-'.now()->format('Ymd-His').'.xlsx';
        $rows = $this->filteredQuery($request)->get()->map(fn (AssessmentResult $result) => $this->row($result))->all();

        try {
            $response = Excel::download(new AssessmentResultsExport(self::HEADERS, $rows), $filename);
            $this->recordExport($request, 'excel', $filename, 'generated');

            return $response;
        } catch (\Throwable $exception) {
            report($exception);
            $this->recordExport($request, 'excel', null, 'failed');

            return back()->with('warning', 'Excel export failed safely. Please review the application log and try again.');
        }
    }

    public function pdf(Request $request): Response|RedirectResponse
    {
        $this->validateFilters($request);
        $filename = 'akhlak360-report-'.now()->format('Ymd-His').'.pdf';
        $results = $this->filteredQuery($request)->get();
        try {
            $response = Pdf::loadView('reports.pdf', compact('results'))
                ->setPaper('a4', 'landscape')
                ->download($filename);
            $this->recordExport($request, 'pdf', $filename, 'generated');

            return $response;
        } catch (\Throwable $exception) {
            report($exception);
            $this->recordExport($request, 'pdf', null, 'failed');

            return back()->with('warning', 'PDF export failed safely. Please review the application log and try again.');
        }
    }

    private function filteredQuery(Request $request): Builder
    {
        return AssessmentResult::query()
            ->with(['assessmentPeriod', 'employee.department', 'employee.position'])
            ->with(['employee.idpRecommendations' => fn ($query) => $query
                ->when($request->filled('period_id'), fn ($query) => $query->where('assessment_period_id', $request->integer('period_id')))])
            ->when($request->filled('period_id'), fn (Builder $query) => $query->where('assessment_period_id', $request->integer('period_id')))
            ->when($request->filled('department_id'), fn (Builder $query) => $query->whereHas(
                'employee',
                fn (Builder $employeeQuery) => $employeeQuery->where('department_id', $request->integer('department_id'))
            ))
            ->when($request->filled('category'), fn (Builder $query) => $query->where('category', $request->category))
            ->when($request->boolean('below_threshold'), fn (Builder $query) => $query->whereRaw(
                'assessment_results.final_score < (select threshold_score from assessment_periods where assessment_periods.id = assessment_results.assessment_period_id)'
            ))
            ->join('employees', 'employees.id', '=', 'assessment_results.employee_id')
            ->orderBy('employees.name')
            ->select('assessment_results.*');
    }

    private function row(AssessmentResult $result): array
    {
        $idp = $result->employee?->idpRecommendations->first();

        return [
            $result->assessmentPeriod?->name,
            $result->employee?->employee_number,
            $result->employee?->name,
            $result->employee?->department?->name,
            $result->employee?->position?->name,
            $result->amanah_score,
            $result->kompeten_score,
            $result->harmonis_score,
            $result->loyal_score,
            $result->adaptif_score,
            $result->kolaboratif_score,
            $result->self_score,
            $result->others_score,
            $result->gap_score,
            $result->final_score,
            $result->category,
            $result->talent_mapping_category,
            $idp?->weakest_core_value,
            $idp?->recommendation,
        ];
    }

    private function recordExport(Request $request, string $type, ?string $path, string $status): void
    {
        ReportExport::create([
            'user_id' => $request->user()->id,
            'assessment_period_id' => $request->filled('period_id') ? $request->integer('period_id') : null,
            'report_type' => $type,
            'file_path' => $path,
            'status' => $status,
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'export_'.$type,
            'module' => 'reports',
            'description' => "Report {$type} export {$status}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'period_id' => ['nullable', 'integer', 'exists:assessment_periods,id'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'below_threshold' => ['nullable', 'boolean'],
        ]);
    }
}
