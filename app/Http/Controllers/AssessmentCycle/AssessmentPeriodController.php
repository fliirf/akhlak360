<?php

namespace App\Http\Controllers\AssessmentCycle;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\AuditLog;
use App\Services\AssessmentResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssessmentPeriodController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'closed'])],
        ]);

        $periods = AssessmentPeriod::query()
            ->withCount(['assignments', 'weights'])
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$request->search.'%')
                ->orWhere('semester', 'like', '%'.$request->search.'%')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->orderByDesc('year')
            ->orderByDesc('start_date')
            ->paginate(10)
            ->withQueryString();

        return view('assessment-cycle.periods.index', compact('periods'));
    }

    public function create(): View
    {
        return view('assessment-cycle.periods.create', [
            'period' => new AssessmentPeriod([
                'semester' => 'Semester 1',
                'year' => now()->year,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(13)->toDateString(),
                'status' => 'draft',
                'threshold_score' => 3.00,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        if ($data['status'] === 'active' && AssessmentPeriod::active()->exists()) {
            return back()
                ->withInput()
                ->withErrors(['status' => 'Only one assessment period can be active at a time.']);
        }

        $period = AssessmentPeriod::create($data);

        $this->audit($request, 'create', "Created assessment period {$period->name}.");

        return redirect()
            ->route('assessment-cycle.periods.index')
            ->with('success', 'Assessment period created successfully.');
    }

    public function edit(AssessmentPeriod $period): View
    {
        return view('assessment-cycle.periods.edit', compact('period'));
    }

    public function update(Request $request, AssessmentPeriod $period): RedirectResponse
    {
        $data = $this->validatedData($request);

        if ($data['status'] === 'active' && AssessmentPeriod::active()->whereKeyNot($period->id)->exists()) {
            return back()
                ->withInput()
                ->withErrors(['status' => 'Only one assessment period can be active at a time.']);
        }

        $period->update($data);

        $this->audit($request, 'update', "Updated assessment period {$period->name}.");

        return redirect()
            ->route('assessment-cycle.periods.index')
            ->with('success', 'Assessment period updated successfully.');
    }

    public function destroy(Request $request, AssessmentPeriod $period): RedirectResponse
    {
        if ($period->assignments()->exists()) {
            $period->update(['status' => 'closed']);
            $this->audit($request, 'close', "Closed assessment period {$period->name} because assignments exist.");

            return redirect()
                ->route('assessment-cycle.periods.index')
                ->with('warning', 'Assessment period has assignments/responses, so it was closed instead of deleted.');
        }

        $name = $period->name;
        $period->delete();

        $this->audit($request, 'delete', "Deleted assessment period {$name}.");

        return redirect()
            ->route('assessment-cycle.periods.index')
            ->with('success', 'Assessment period deleted successfully.');
    }

    public function recalculate(Request $request, AssessmentPeriod $period, AssessmentResultService $resultService): RedirectResponse
    {
        $count = $resultService->calculateForPeriod($period->id, $request->user()?->id);

        $this->audit($request, 'recalculate_results', "Recalculated {$count} assessment results for {$period->name}.");

        return redirect()
            ->route('assessment-cycle.periods.index')
            ->with('success', "Recalculated {$count} employee results for {$period->name}.");
    }

    public function close(Request $request, AssessmentPeriod $period, AssessmentResultService $resultService): RedirectResponse
    {
        if ($period->status === 'closed') {
            return redirect()
                ->route('assessment-cycle.periods.index')
                ->with('warning', "{$period->name} is already closed.");
        }

        $count = $resultService->calculateForPeriod($period->id, $request->user()?->id);
        $period->update(['status' => 'closed']);

        $this->audit($request, 'close', "Closed assessment period {$period->name} after recalculating {$count} employee results.");

        return redirect()
            ->route('assessment-cycle.periods.index')
            ->with('success', "{$period->name} closed successfully after recalculating {$count} employee results.");
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'semester' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['required', Rule::in(['draft', 'active', 'closed'])],
            'threshold_score' => ['required', 'numeric', 'min:1', 'max:5'],
        ]);
    }

    private function audit(Request $request, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module' => 'assessment_periods',
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
