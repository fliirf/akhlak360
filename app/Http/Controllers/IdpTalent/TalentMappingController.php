<?php

namespace App\Http\Controllers\IdpTalent;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentResult;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TalentMappingController extends Controller
{
    private const CATEGORIES = [
        'High Potential',
        'Solid Contributor',
        'Core Contributor',
        'Need Development',
    ];

    public function index(Request $request): View
    {
        $validated = $this->validateFilters($request);
        $periods = AssessmentPeriod::orderByDesc('year')->orderByDesc('start_date')->get();
        $departments = Department::active()->orderBy('name')->get();
        $selectedPeriod = isset($validated['period_id'])
            ? (int) $validated['period_id']
            : ($periods->firstWhere('status', 'active') ?? $periods->first())?->id;
        $selectedDepartment = isset($validated['department_id']) ? (int) $validated['department_id'] : null;
        $allResults = $this->filteredQuery($request, $selectedPeriod, $selectedDepartment)->get();
        $results = $this->filteredQuery($request, $selectedPeriod, $selectedDepartment)
            ->paginate(15)
            ->withQueryString();

        return view('idp-talent.talent-mapping.index', [
            'periods' => $periods,
            'departments' => $departments,
            'selectedPeriod' => $selectedPeriod,
            'selectedDepartment' => $selectedDepartment,
            'results' => $results,
            'categoryCounts' => $this->categoryCounts($allResults),
            'categoryChart' => [
                'labels' => self::CATEGORIES,
                'data' => $this->categoryCounts($allResults)->values()->all(),
            ],
            'canExport' => $request->user()->hasRole(['admin_hr', 'management']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasRole(['admin_hr', 'management']), 403);
        $validated = $this->validateFilters($request);
        $selectedPeriod = isset($validated['period_id']) ? (int) $validated['period_id'] : null;
        $selectedDepartment = isset($validated['department_id']) ? (int) $validated['department_id'] : null;
        $filename = 'talent-mapping-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request, $selectedPeriod, $selectedDepartment): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'employee_name',
                'department',
                'period',
                'final_score',
                'gap_score',
                'talent_mapping_category',
                'idp_status',
            ]);

            $this->filteredQuery($request, $selectedPeriod, $selectedDepartment)
                ->chunk(100, function (Collection $results) use ($handle): void {
                    foreach ($results as $result) {
                        fputcsv($handle, [
                            $result->employee?->name,
                            $result->employee?->department?->name,
                            $result->assessmentPeriod?->name,
                            $result->final_score,
                            $result->gap_score,
                            $result->talent_mapping_category,
                            $result->employee?->idpRecommendations->first()?->status ?? '-',
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function filteredQuery(Request $request, ?int $periodId, ?int $departmentId): Builder
    {
        return AssessmentResult::query()
            ->with(['assessmentPeriod', 'employee.department'])
            ->with(['employee.idpRecommendations' => fn ($query) => $query->when($periodId, fn ($query) => $query->where('assessment_period_id', $periodId))])
            ->whereNotNull('talent_mapping_category')
            ->when($periodId, fn (Builder $query) => $query->where('assessment_period_id', $periodId))
            ->when($departmentId, fn (Builder $query) => $query->whereHas(
                'employee',
                fn (Builder $employeeQuery) => $employeeQuery->where('department_id', $departmentId)
            ))
            ->when($request->user()->hasRole('employee'), fn (Builder $query) => $query
                ->where('assessment_results.employee_id', $request->user()->employee?->id ?? 0))
            ->when($request->user()->hasRole('supervisor'), fn (Builder $query) => $query->where(function (Builder $query) use ($request) {
                $supervisorId = $request->user()->employee?->id ?? 0;
                $query->where('assessment_results.employee_id', $supervisorId)
                    ->orWhereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->where('supervisor_id', $supervisorId));
            }))
            ->join('employees', 'employees.id', '=', 'assessment_results.employee_id')
            ->orderBy('employees.name')
            ->select('assessment_results.*');
    }

    private function categoryCounts(Collection $results): Collection
    {
        $grouped = $results->groupBy('talent_mapping_category')->map->count();

        return collect(self::CATEGORIES)
            ->mapWithKeys(fn (string $category) => [$category => $grouped[$category] ?? 0]);
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'period_id' => ['nullable', 'integer', 'exists:assessment_periods,id'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
        ]);
    }
}
