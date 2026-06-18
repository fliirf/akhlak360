<?php

namespace Tests\Feature;

use App\Models\AssessmentPeriod;
use App\Models\AssessmentResult;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GapAnalysisPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_gap_analysis_page_filters_and_shows_interpretations(): void
    {
        $user = User::factory()->create(['role' => 'admin_hr']);
        $operations = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $finance = Department::create(['name' => 'Finance', 'code' => 'FIN']);
        $period = AssessmentPeriod::create([
            'name' => 'Semester 1 2026',
            'semester' => 'Semester 1',
            'year' => 2026,
            'start_date' => '2026-06-17',
            'end_date' => '2026-06-30',
            'status' => 'active',
            'threshold_score' => 3.00,
        ]);

        $higher = $this->employee($operations, 'EMP-GAP-001', 'Higher Self');
        $lower = $this->employee($operations, 'EMP-GAP-002', 'Lower Self');
        $aligned = $this->employee($finance, 'EMP-GAP-003', 'Aligned Person');

        $this->assessmentResult($period, $higher, 4.50, 3.50, 1.00);
        $this->assessmentResult($period, $lower, 2.50, 3.50, -1.00);
        $this->assessmentResult($period, $aligned, 3.40, 3.20, 0.20);

        $this->actingAs($user)
            ->get("/analytics/gap-analysis?period_id={$period->id}&department_id={$operations->id}")
            ->assertOk()
            ->assertSee('Gap Analysis')
            ->assertSee('EMP-GAP-001')
            ->assertSee('Higher Self')
            ->assertSee('Penilaian diri lebih tinggi')
            ->assertSee('EMP-GAP-002')
            ->assertSee('Penilaian diri lebih rendah')
            ->assertDontSee('Aligned Person');

        $this->actingAs($user)
            ->get("/analytics/gap-analysis?period_id={$period->id}")
            ->assertOk()
            ->assertSee('Selaras')
            ->assertSee('averageGapChart')
            ->assertSee('gapDistributionChart');
    }

    private function employee(Department $department, string $number, string $name): Employee
    {
        return Employee::create([
            'department_id' => $department->id,
            'employee_number' => $number,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'employment_status' => 'active',
        ]);
    }

    private function assessmentResult(AssessmentPeriod $period, Employee $employee, float $selfScore, float $othersScore, float $gapScore): AssessmentResult
    {
        return AssessmentResult::create([
            'assessment_period_id' => $period->id,
            'employee_id' => $employee->id,
            'amanah_score' => 3.50,
            'kompeten_score' => 3.50,
            'harmonis_score' => 3.50,
            'loyal_score' => 3.50,
            'adaptif_score' => 3.50,
            'kolaboratif_score' => 3.50,
            'self_score' => $selfScore,
            'others_score' => $othersScore,
            'gap_score' => $gapScore,
            'final_score' => 3.50,
            'category' => 'Cukup',
            'talent_mapping_category' => 'Core Contributor',
        ]);
    }
}
