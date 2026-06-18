<?php

namespace Tests\Feature;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentPeriod;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentCycleCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin_hr',
        ]);
    }

    public function test_period_crud_validates_dates_and_single_active_period(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/assessment-cycle/periods', [
                'name' => 'Invalid Period',
                'semester' => 'Semester 1',
                'year' => 2026,
                'start_date' => '2026-06-16',
                'end_date' => '2026-06-15',
                'status' => 'draft',
                'threshold_score' => 3.00,
            ])
            ->assertSessionHasErrors('end_date');

        $this->actingAs($admin)
            ->post('/assessment-cycle/periods', [
                'name' => 'Semester 1 2026',
                'semester' => 'Semester 1',
                'year' => 2026,
                'start_date' => '2026-06-16',
                'end_date' => '2026-06-29',
                'status' => 'active',
                'threshold_score' => 3.00,
            ])
            ->assertRedirect('/assessment-cycle/periods');

        $this->actingAs($admin)
            ->post('/assessment-cycle/periods', [
                'name' => 'Semester 2 2026',
                'semester' => 'Semester 2',
                'year' => 2026,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-14',
                'status' => 'active',
                'threshold_score' => 3.00,
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('audit_logs', ['module' => 'assessment_periods', 'action' => 'create']);
    }

    public function test_period_with_assignments_is_closed_instead_of_deleted(): void
    {
        $admin = $this->admin();
        $department = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $assessor = Employee::create([
            'department_id' => $department->id,
            'employee_number' => 'A-001',
            'name' => 'Assessor',
            'employment_status' => 'active',
        ]);
        $assessee = Employee::create([
            'department_id' => $department->id,
            'employee_number' => 'E-001',
            'name' => 'Assessee',
            'employment_status' => 'active',
        ]);
        $period = AssessmentPeriod::create([
            'name' => 'Semester 1 2026',
            'semester' => 'Semester 1',
            'year' => 2026,
            'start_date' => '2026-06-16',
            'end_date' => '2026-06-29',
            'status' => 'active',
            'threshold_score' => 3.00,
        ]);

        AssessmentAssignment::create([
            'assessment_period_id' => $period->id,
            'assessor_employee_id' => $assessor->id,
            'assessee_employee_id' => $assessee->id,
            'assessor_type' => 'peer',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->delete("/assessment-cycle/periods/{$period->id}")
            ->assertRedirect('/assessment-cycle/periods');

        $this->assertSame('closed', $period->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['module' => 'assessment_periods', 'action' => 'close']);
    }

    public function test_weight_configuration_requires_total_100_and_saves_per_period(): void
    {
        $admin = $this->admin();
        $period = AssessmentPeriod::create([
            'name' => 'Semester 1 2026',
            'semester' => 'Semester 1',
            'year' => 2026,
            'start_date' => '2026-06-16',
            'end_date' => '2026-06-29',
            'status' => 'active',
            'threshold_score' => 3.00,
        ]);

        $this->actingAs($admin)
            ->post('/assessment-cycle/weights', [
                'assessment_period_id' => $period->id,
                'weights' => [
                    'supervisor' => 40,
                    'peer' => 20,
                    'subordinate' => 20,
                    'self' => 10,
                ],
            ])
            ->assertSessionHasErrors('weights');

        $this->actingAs($admin)
            ->post('/assessment-cycle/weights', [
                'assessment_period_id' => $period->id,
                'weights' => [
                    'supervisor' => 40,
                    'peer' => 20,
                    'subordinate' => 30,
                    'self' => 10,
                ],
            ])
            ->assertRedirect('/assessment-cycle/weights?assessment_period_id='.$period->id);

        $this->assertDatabaseHas('assessment_weights', [
            'assessment_period_id' => $period->id,
            'assessor_type' => 'supervisor',
            'weight' => 40,
        ]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'assessment_weights', 'action' => 'update']);
    }

    public function test_admin_can_explicitly_close_period_and_action_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);
        $period = AssessmentPeriod::create([
            'name' => 'Closable Period',
            'semester' => 'Semester 1',
            'year' => 2026,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-14',
            'status' => 'active',
            'threshold_score' => 3,
        ]);

        $this->actingAs($admin)
            ->patch("/assessment-cycle/periods/{$period->id}/close")
            ->assertRedirect('/assessment-cycle/periods')
            ->assertSessionHas('success');

        $this->assertSame('closed', $period->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'module' => 'assessment_periods',
            'action' => 'close',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'employee']))
            ->patch("/assessment-cycle/periods/{$period->id}/close")
            ->assertForbidden();
    }
}
