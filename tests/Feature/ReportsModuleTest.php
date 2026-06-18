<?php

namespace Tests\Feature;

use App\Models\AssessmentPeriod;
use App\Models\AssessmentResult;
use App\Models\Department;
use App\Models\Employee;
use App\Models\IdpRecommendation;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_page_filters_results(): void
    {
        $user = User::factory()->create(['role' => 'admin_hr']);
        $fixture = $this->fixture();
        $included = $this->employee($fixture['department'], $fixture['position'], 'EMP-REP-001', 'Report Included');
        $excluded = $this->employee($fixture['otherDepartment'], $fixture['position'], 'EMP-REP-002', 'Report Excluded');

        $this->assessmentResult($fixture['period'], $included, 2.80, 'Perlu Pengembangan');
        $this->assessmentResult($fixture['period'], $excluded, 4.20, 'Baik');

        $this->actingAs($user)
            ->get("/reports/export?period_id={$fixture['period']->id}&department_id={$fixture['department']->id}&category=Perlu%20Pengembangan&below_threshold=1")
            ->assertOk()
            ->assertSee('Export Reports')
            ->assertSee('Report Included')
            ->assertDontSee('Report Excluded')
            ->assertSee('CSV');
    }

    public function test_csv_export_contains_required_columns_and_logs_activity(): void
    {
        $user = User::factory()->create(['role' => 'management']);
        $fixture = $this->fixture();
        $employee = $this->employee($fixture['department'], $fixture['position'], 'EMP-REP-003', 'CSV Employee');
        $this->assessmentResult($fixture['period'], $employee, 4.00, 'Baik');
        IdpRecommendation::create([
            'assessment_period_id' => $fixture['period']->id,
            'employee_id' => $employee->id,
            'weakest_core_value' => 'Kompeten',
            'recommendation' => 'Technical training, continuous learning plan, and knowledge sharing session.',
            'status' => 'draft',
        ]);

        $content = $this->actingAs($user)
            ->get("/reports/export/csv?period_id={$fixture['period']->id}&department_id={$fixture['department']->id}")
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString('period,employee_number,employee_name,department,position,amanah_score,kompeten_score,harmonis_score,loyal_score,adaptif_score,kolaboratif_score,self_score,others_score,gap_score,final_score,category,talent_mapping_category,weakest_core_value,idp_recommendation', $content);
        $this->assertStringContainsString('EMP-REP-003', $content);
        $this->assertStringContainsString('Technical training', $content);
        $this->assertDatabaseHas('report_exports', [
            'user_id' => $user->id,
            'assessment_period_id' => $fixture['period']->id,
            'report_type' => 'csv',
            'status' => 'generated',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'reports',
            'action' => 'export_csv',
        ]);

        $this->actingAs($user)
            ->get('/reports/history')
            ->assertOk()
            ->assertSee('Export History')
            ->assertSee('CSV');
    }

    public function test_excel_and_pdf_exports_are_real_filtered_downloads_and_record_activity(): void
    {
        $user = User::factory()->create(['role' => 'admin_hr']);
        $fixture = $this->fixture();
        $included = $this->employee($fixture['department'], $fixture['position'], 'EMP-XLSX-001', 'Included Export');
        $excluded = $this->employee($fixture['otherDepartment'], $fixture['position'], 'EMP-XLSX-002', 'Excluded Export');
        $this->assessmentResult($fixture['period'], $included, 4.10, 'Baik');
        $this->assessmentResult($fixture['period'], $excluded, 2.50, 'Perlu Pengembangan');

        $excel = $this->actingAs($user)
            ->get("/reports/export/excel?period_id={$fixture['period']->id}&department_id={$fixture['department']->id}")
            ->assertOk()
            ->assertDownload();
        $this->assertStringStartsWith('PK', file_get_contents($excel->baseResponse->getFile()->getPathname()));

        $pdf = $this->actingAs($user)
            ->get("/reports/export/pdf?period_id={$fixture['period']->id}&department_id={$fixture['department']->id}")
            ->assertOk()
            ->assertDownload();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->assertDatabaseHas('report_exports', [
            'user_id' => $user->id,
            'report_type' => 'excel',
            'status' => 'generated',
        ]);
        $this->assertDatabaseHas('report_exports', [
            'user_id' => $user->id,
            'report_type' => 'pdf',
            'status' => 'generated',
        ]);
    }

    public function test_talent_category_filter_applies_to_preview_and_csv(): void
    {
        $user = User::factory()->create(['role' => 'management']);
        $fixture = $this->fixture();
        $included = $this->employee($fixture['department'], $fixture['position'], 'EMP-TAL-001', 'High Potential Employee');
        $excluded = $this->employee($fixture['department'], $fixture['position'], 'EMP-TAL-002', 'Core Contributor Employee');
        $includedResult = $this->assessmentResult($fixture['period'], $included, 4.8, 'Sangat Baik');
        $includedResult->update(['talent_mapping_category' => 'High Potential']);
        $excludedResult = $this->assessmentResult($fixture['period'], $excluded, 3.2, 'Cukup');
        $excludedResult->update(['talent_mapping_category' => 'Core Contributor']);

        $query = http_build_query([
            'period_id' => $fixture['period']->id,
            'talent_category' => 'High Potential',
        ]);

        $this->actingAs($user)
            ->get("/reports/export?{$query}")
            ->assertOk()
            ->assertSee('High Potential Employee')
            ->assertDontSee('Core Contributor Employee');

        $content = $this->actingAs($user)
            ->get("/reports/export/csv?{$query}")
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('High Potential Employee', $content);
        $this->assertStringNotContainsString('Core Contributor Employee', $content);
    }

    private function fixture(): array
    {
        $department = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $otherDepartment = Department::create(['name' => 'Finance', 'code' => 'FIN']);
        $position = Position::create(['name' => 'Staff', 'level' => '1']);
        $period = AssessmentPeriod::create([
            'name' => 'Semester 1 2026',
            'semester' => 'Semester 1',
            'year' => 2026,
            'start_date' => '2026-06-17',
            'end_date' => '2026-06-30',
            'status' => 'active',
            'threshold_score' => 3.00,
        ]);

        return compact('department', 'otherDepartment', 'position', 'period');
    }

    private function employee(Department $department, Position $position, string $number, string $name): Employee
    {
        return Employee::create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => $number,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'employment_status' => 'active',
        ]);
    }

    private function assessmentResult(AssessmentPeriod $period, Employee $employee, float $finalScore, string $category): AssessmentResult
    {
        return AssessmentResult::create([
            'assessment_period_id' => $period->id,
            'employee_id' => $employee->id,
            'amanah_score' => $finalScore,
            'kompeten_score' => $finalScore,
            'harmonis_score' => $finalScore,
            'loyal_score' => $finalScore,
            'adaptif_score' => $finalScore,
            'kolaboratif_score' => $finalScore,
            'self_score' => $finalScore,
            'others_score' => $finalScore,
            'gap_score' => 0,
            'final_score' => $finalScore,
            'category' => $category,
            'talent_mapping_category' => $finalScore < 3 ? 'Need Development' : 'Solid Contributor',
        ]);
    }
}
