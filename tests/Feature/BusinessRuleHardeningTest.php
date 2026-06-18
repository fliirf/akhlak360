<?php

namespace Tests\Feature;

use App\Http\Controllers\Assessment\AssessmentFormController;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentResult;
use App\Models\AssessmentWeight;
use App\Models\Department;
use App\Models\Employee;
use App\Models\IdpRecommendation;
use App\Models\PeerApproval;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessRuleHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_duration_cannot_exceed_fourteen_inclusive_days(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);

        $this->actingAs($admin)
            ->post('/assessment-cycle/periods', [
                'name' => 'Too Long',
                'semester' => 'Semester 1',
                'year' => 2026,
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-15',
                'status' => 'draft',
                'threshold_score' => 3,
            ])
            ->assertSessionHasErrors('end_date');

        $this->assertDatabaseMissing('assessment_periods', ['name' => 'Too Long']);
    }

    public function test_closed_future_and_expired_period_assignments_cannot_be_opened_or_submitted(): void
    {
        [$user, $employee, $assessee] = $this->assessmentEmployees();

        foreach ([
            ['closed', now()->subDays(10), now()->subDay()],
            ['active', now()->addDay(), now()->addDays(10)],
            ['active', now()->subDays(10), now()->subDay()],
        ] as $index => [$status, $start, $end]) {
            $period = $this->period("Blocked {$index}", $status, $start, $end);
            $assignment = AssessmentAssignment::create([
                'assessment_period_id' => $period->id,
                'assessor_employee_id' => $employee->id,
                'assessee_employee_id' => $assessee->id,
                'assessor_type' => 'peer',
                'status' => 'pending',
            ]);

            $this->actingAs($user)
                ->get("/assessment/assignments/{$assignment->id}/fill")
                ->assertRedirect('/assessment/pending');

            $this->actingAs($user)
                ->post("/assessment/assignments/{$assignment->id}/submit", $this->completeScores())
                ->assertRedirect('/assessment/pending');

            $this->assertSame('pending', $assignment->fresh()->status);
            $this->assertCount(0, $assignment->fresh()->responses);
        }

        $this->actingAs($user)
            ->get('/assessment/pending')
            ->assertOk()
            ->assertDontSee('Blocked 0')
            ->assertDontSee('Blocked 1')
            ->assertDontSee('Blocked 2');
    }

    public function test_manual_assignment_is_limited_to_open_period_and_forced_pending(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);
        [, $assessor, $assessee] = $this->assessmentEmployees();
        $open = $this->period('Open Period', 'active', now()->subDay(), now()->addDays(5));
        $closed = $this->period('Closed Period', 'closed', now()->subDays(10), now()->subDay());

        $this->actingAs($admin)
            ->post('/assessment-cycle/assign-assessors', [
                'assessment_period_id' => $closed->id,
                'assessor_employee_id' => $assessor->id,
                'assessee_employee_id' => $assessee->id,
                'assessor_type' => 'peer',
                'status' => 'submitted',
            ])
            ->assertSessionHasErrors('assessment_period_id');

        $this->actingAs($admin)
            ->post('/assessment-cycle/assign-assessors', [
                'assessment_period_id' => $open->id,
                'assessor_employee_id' => $assessor->id,
                'assessee_employee_id' => $assessee->id,
                'assessor_type' => 'peer',
                'status' => 'submitted',
            ])
            ->assertRedirect('/assessment-cycle/assign-assessors');

        $this->assertDatabaseHas('assessment_assignments', [
            'assessment_period_id' => $open->id,
            'status' => 'pending',
            'submitted_at' => null,
        ]);
    }

    public function test_peer_approval_rechecks_active_people_period_and_pending_state(): void
    {
        $department = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $supervisorUser = User::factory()->create(['role' => 'supervisor']);
        $supervisor = $this->employee($department, 'SUP-HARD', 'Supervisor', 'active', $supervisorUser);
        $assessee = $this->employee($department, 'EMP-HARD', 'Employee', 'active', supervisor: $supervisor);
        $inactivePeer = $this->employee($department, 'PEER-OLD', 'Inactive Peer', 'inactive', supervisor: $supervisor);
        $admin = User::factory()->create(['role' => 'admin_hr']);
        $period = $this->period('Peer Period', 'active', now()->subDay(), now()->addDays(5));

        $this->actingAs($admin)
            ->post('/assessment-cycle/peer-approval', [
                'assessment_period_id' => $period->id,
                'employee_id' => $assessee->id,
                'peer_employee_id' => $inactivePeer->id,
            ])
            ->assertSessionHasErrors('peer_employee_id');

        $activePeer = $this->employee($department, 'PEER-NEW', 'Active Peer', 'active', supervisor: $supervisor);
        $approval = PeerApproval::create([
            'assessment_period_id' => $period->id,
            'employee_id' => $assessee->id,
            'peer_employee_id' => $activePeer->id,
            'supervisor_employee_id' => $supervisor->id,
            'status' => 'pending',
        ]);
        $period->update(['status' => 'closed']);

        $this->actingAs($supervisorUser)
            ->patch("/assessment-cycle/peer-approval/{$approval->id}/approve")
            ->assertRedirect();

        $this->assertSame('pending', $approval->fresh()->status);
        $this->assertDatabaseMissing('assessment_assignments', [
            'assessment_period_id' => $period->id,
            'assessor_employee_id' => $activePeer->id,
            'assessee_employee_id' => $assessee->id,
        ]);
    }

    public function test_subordinate_generation_uses_direct_reports_not_position_title(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);
        $department = Department::create(['name' => 'Strategy', 'code' => 'STR']);
        $leaderPosition = Position::create(['name' => 'Project Lead', 'level' => 'L3']);
        $leader = $this->employee($department, 'LEAD-1', 'Project Lead', position: $leaderPosition);
        $subordinate = $this->employee($department, 'SUB-1', 'Team Member', supervisor: $leader);
        $period = $this->period('Generation Period', 'active', now()->subDay(), now()->addDays(5));

        $this->actingAs($admin)
            ->post('/assessment-cycle/assign-assessors/generate-subordinate', [
                'assessment_period_id' => $period->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assessment_assignments', [
            'assessment_period_id' => $period->id,
            'assessor_employee_id' => $subordinate->id,
            'assessee_employee_id' => $leader->id,
            'assessor_type' => 'subordinate',
        ]);
    }

    public function test_generators_and_peer_proposals_require_a_currently_open_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);
        $department = Department::create(['name' => 'Future Operations', 'code' => 'FOP']);
        $supervisor = $this->employee($department, 'F-SUP', 'Future Supervisor');
        $employee = $this->employee($department, 'F-EMP', 'Future Employee', supervisor: $supervisor);
        $peer = $this->employee($department, 'F-PEER', 'Future Peer', supervisor: $supervisor);
        $futurePeriod = $this->period('Future Active', 'active', now()->addDay(), now()->addDays(10));

        $this->actingAs($admin)
            ->post('/assessment-cycle/assign-assessors/generate-self', [
                'assessment_period_id' => $futurePeriod->id,
            ])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->post('/assessment-cycle/peer-approval', [
                'assessment_period_id' => $futurePeriod->id,
                'employee_id' => $employee->id,
                'peer_employee_id' => $peer->id,
            ])
            ->assertSessionHasErrors('assessment_period_id');

        $this->assertDatabaseMissing('assessment_assignments', [
            'assessment_period_id' => $futurePeriod->id,
        ]);
        $this->assertDatabaseMissing('peer_approvals', [
            'assessment_period_id' => $futurePeriod->id,
        ]);
    }

    public function test_period_with_non_assignment_related_data_is_closed_instead_of_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);
        $period = $this->period('Weighted Draft', 'draft', now()->addMonth(), now()->addMonth()->addDays(10));
        AssessmentWeight::create([
            'assessment_period_id' => $period->id,
            'assessor_type' => 'self',
            'weight' => 100,
        ]);

        $this->actingAs($admin)
            ->delete("/assessment-cycle/periods/{$period->id}")
            ->assertRedirect('/assessment-cycle/periods')
            ->assertSessionHas('warning');

        $this->assertSame('closed', $period->fresh()->status);
        $this->assertDatabaseHas('assessment_weights', [
            'assessment_period_id' => $period->id,
            'assessor_type' => 'self',
        ]);
    }

    public function test_report_preview_uses_idp_from_the_same_result_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);
        $department = Department::create(['name' => 'Finance', 'code' => 'FIN']);
        $employee = $this->employee($department, 'REP-1', 'Report Employee');
        $older = $this->period('Older Period', 'closed', now()->subMonths(7), now()->subMonths(7)->addDays(10));
        $newer = $this->period('Newer Period', 'closed', now()->subMonth(), now()->subMonth()->addDays(10));

        foreach ([[$older, 'Amanah'], [$newer, 'Adaptif']] as [$period, $weakest]) {
            AssessmentResult::create([
                'assessment_period_id' => $period->id,
                'employee_id' => $employee->id,
                'final_score' => 3.5,
                'category' => 'Cukup',
            ]);
            IdpRecommendation::create([
                'assessment_period_id' => $period->id,
                'employee_id' => $employee->id,
                'weakest_core_value' => $weakest,
                'recommendation' => "Develop {$weakest}",
                'status' => 'draft',
            ]);
        }

        $response = $this->actingAs($admin)->get('/reports/export');
        $response->assertOk();

        $olderPosition = strpos($response->getContent(), 'Older Period');
        $olderIdpPosition = strpos($response->getContent(), 'Amanah', $olderPosition);
        $newerPosition = strpos($response->getContent(), 'Newer Period');
        $newerIdpPosition = strpos($response->getContent(), 'Adaptif', $newerPosition);

        $this->assertNotFalse($olderPosition);
        $this->assertNotFalse($olderIdpPosition);
        $this->assertNotFalse($newerPosition);
        $this->assertNotFalse($newerIdpPosition);
    }

    private function assessmentEmployees(): array
    {
        $department = Department::firstOrCreate(['code' => 'AUD'], ['name' => 'Audit']);
        $user = User::factory()->create(['role' => 'employee']);
        $assessor = $this->employee($department, 'ASSESSOR-1', 'Assessor', 'active', $user);
        $assessee = $this->employee($department, 'ASSESSEE-1', 'Assessee');

        return [$user, $assessor, $assessee];
    }

    private function employee(
        Department $department,
        string $number,
        string $name,
        string $status = 'active',
        ?User $user = null,
        ?Employee $supervisor = null,
        ?Position $position = null
    ): Employee {
        return Employee::create([
            'user_id' => $user?->id,
            'department_id' => $department->id,
            'position_id' => $position?->id,
            'employee_number' => $number,
            'name' => $name,
            'email' => strtolower($number).'@example.com',
            'supervisor_id' => $supervisor?->id,
            'employment_status' => $status,
        ]);
    }

    private function period(string $name, string $status, mixed $start, mixed $end): AssessmentPeriod
    {
        return AssessmentPeriod::create([
            'name' => $name,
            'semester' => 'Semester 1',
            'year' => 2026,
            'start_date' => $start,
            'end_date' => $end,
            'status' => $status,
            'threshold_score' => 3,
        ]);
    }

    private function completeScores(): array
    {
        $scores = [];

        foreach (AssessmentFormController::INDICATORS as $coreValue => $indicators) {
            foreach ($indicators as $index => $indicator) {
                $scores[$coreValue][$index] = 4;
            }
        }

        return ['scores' => $scores];
    }
}
