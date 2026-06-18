<?php

namespace Tests\Feature;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentResponse;
use App\Models\AssessmentResult;
use App\Models\AssessmentWeight;
use App\Models\Department;
use App\Models\Employee;
use App\Models\IdpRecommendation;
use App\Models\User;
use App\Services\AssessmentResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentResultServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_weighted_scores_normalizes_missing_types_and_generates_idp(): void
    {
        $fixture = $this->fixture();

        foreach ([
            'supervisor' => 40,
            'peer' => 20,
            'subordinate' => 30,
            'self' => 10,
        ] as $type => $weight) {
            AssessmentWeight::create([
                'assessment_period_id' => $fixture['period']->id,
                'assessor_type' => $type,
                'weight' => $weight,
            ]);
        }

        $this->submittedAssignment($fixture['period'], $fixture['self'], $fixture['assessee'], 'self', [
            'Amanah' => 3,
            'Kompeten' => 5,
            'Harmonis' => 5,
            'Loyal' => 5,
            'Adaptif' => 5,
            'Kolaboratif' => 5,
        ]);
        $this->submittedAssignment($fixture['period'], $fixture['supervisor'], $fixture['assessee'], 'supervisor', [
            'Amanah' => 3,
            'Kompeten' => 4,
            'Harmonis' => 4,
            'Loyal' => 4,
            'Adaptif' => 4,
            'Kolaboratif' => 4,
        ]);
        $this->submittedAssignment($fixture['period'], $fixture['peerOne'], $fixture['assessee'], 'peer', [
            'Amanah' => 2,
            'Kompeten' => 3,
            'Harmonis' => 3,
            'Loyal' => 3,
            'Adaptif' => 3,
            'Kolaboratif' => 3,
        ]);
        $this->submittedAssignment($fixture['period'], $fixture['peerTwo'], $fixture['assessee'], 'peer', [
            'Amanah' => 4,
            'Kompeten' => 5,
            'Harmonis' => 5,
            'Loyal' => 5,
            'Adaptif' => 5,
            'Kolaboratif' => 5,
        ]);

        $result = app(AssessmentResultService::class)->calculateForEmployeePeriod(
            $fixture['assessee']->id,
            $fixture['period']->id,
            $fixture['admin']->id,
        );

        $this->assertSame('3.00', $result->amanah_score);
        $this->assertSame('4.14', $result->kompeten_score);
        $this->assertSame('3.95', $result->final_score);
        $this->assertSame('4.67', $result->self_score);
        $this->assertSame('3.83', $result->others_score);
        $this->assertSame('0.83', $result->gap_score);
        $this->assertSame('Baik', $result->category);
        $this->assertSame('Solid Contributor', $result->talent_mapping_category);
        $this->assertDatabaseHas('idp_recommendations', [
            'assessment_period_id' => $fixture['period']->id,
            'employee_id' => $fixture['assessee']->id,
            'weakest_core_value' => 'Amanah',
            'recommendation' => 'Coaching accountability, commitment management, and ethical responsibility.',
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $fixture['admin']->id,
            'module' => 'assessment_results',
            'action' => 'calculate',
        ]);
    }

    public function test_admin_hr_can_recalculate_period_results(): void
    {
        $fixture = $this->fixture();
        AssessmentWeight::create([
            'assessment_period_id' => $fixture['period']->id,
            'assessor_type' => 'self',
            'weight' => 100,
        ]);
        $this->submittedAssignment($fixture['period'], $fixture['self'], $fixture['assessee'], 'self', [
            'Amanah' => 5,
            'Kompeten' => 5,
            'Harmonis' => 5,
            'Loyal' => 5,
            'Adaptif' => 5,
            'Kolaboratif' => 5,
        ]);

        $this->actingAs($fixture['admin'])
            ->post("/assessment-cycle/periods/{$fixture['period']->id}/recalculate")
            ->assertRedirect('/assessment-cycle/periods')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('assessment_results', [
            'assessment_period_id' => $fixture['period']->id,
            'employee_id' => $fixture['assessee']->id,
            'final_score' => 5,
            'category' => 'Sangat Baik',
            'talent_mapping_category' => 'Solid Contributor',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $fixture['admin']->id,
            'module' => 'assessment_periods',
            'action' => 'recalculate_results',
        ]);
    }

    public function test_recalculation_rejects_incomplete_submissions_and_removes_stale_derived_data(): void
    {
        $fixture = $this->fixture();
        $assignment = AssessmentAssignment::create([
            'assessment_period_id' => $fixture['period']->id,
            'assessor_employee_id' => $fixture['self']->id,
            'assessee_employee_id' => $fixture['assessee']->id,
            'assessor_type' => 'self',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        AssessmentResponse::create([
            'assessment_assignment_id' => $assignment->id,
            'core_value' => 'Amanah',
            'indicator' => 'Incomplete legacy response',
            'score' => 5,
        ]);
        AssessmentResult::create([
            'assessment_period_id' => $fixture['period']->id,
            'employee_id' => $fixture['assessee']->id,
            'final_score' => 5,
            'category' => 'Sangat Baik',
        ]);
        IdpRecommendation::create([
            'assessment_period_id' => $fixture['period']->id,
            'employee_id' => $fixture['assessee']->id,
            'weakest_core_value' => 'Amanah',
            'recommendation' => 'Stale recommendation',
            'status' => 'draft',
        ]);

        $result = app(AssessmentResultService::class)->calculateForEmployeePeriod(
            $fixture['assessee']->id,
            $fixture['period']->id,
            $fixture['admin']->id,
        );

        $this->assertNull($result);
        $this->assertDatabaseMissing('assessment_results', [
            'assessment_period_id' => $fixture['period']->id,
            'employee_id' => $fixture['assessee']->id,
        ]);
        $this->assertDatabaseMissing('idp_recommendations', [
            'assessment_period_id' => $fixture['period']->id,
            'employee_id' => $fixture['assessee']->id,
        ]);
    }

    private function fixture(): array
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);
        $department = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $period = AssessmentPeriod::create([
            'name' => 'Semester 1 2026',
            'semester' => 'Semester 1',
            'year' => 2026,
            'start_date' => '2026-06-17',
            'end_date' => '2026-06-30',
            'status' => 'active',
            'threshold_score' => 3.00,
        ]);

        $assessee = $this->employee($department, 'EMP-R-001', 'Assessee');
        $self = $assessee;
        $supervisor = $this->employee($department, 'EMP-R-002', 'Supervisor');
        $peerOne = $this->employee($department, 'EMP-R-003', 'Peer One');
        $peerTwo = $this->employee($department, 'EMP-R-004', 'Peer Two');

        return compact('admin', 'department', 'period', 'assessee', 'self', 'supervisor', 'peerOne', 'peerTwo');
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

    private function submittedAssignment(AssessmentPeriod $period, Employee $assessor, Employee $assessee, string $type, array $scores): AssessmentAssignment
    {
        $assignment = AssessmentAssignment::create([
            'assessment_period_id' => $period->id,
            'assessor_employee_id' => $assessor->id,
            'assessee_employee_id' => $assessee->id,
            'assessor_type' => $type,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        foreach ($scores as $coreValue => $score) {
            foreach (range(1, 3) as $index) {
                AssessmentResponse::create([
                    'assessment_assignment_id' => $assignment->id,
                    'core_value' => $coreValue,
                    'indicator' => "{$coreValue} indicator {$index}",
                    'score' => $score,
                ]);
            }
        }

        return $assignment;
    }
}
