<?php

namespace Tests\Feature;

use App\Models\AssessmentPeriod;
use App\Models\AssessmentResult;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreValueDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/analytics/core-value-dashboard')
            ->assertRedirect('/sso/login');
    }

    public function test_allowed_roles_can_access_core_value_dashboard(): void
    {
        foreach (['admin_hr', 'management'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/analytics/core-value-dashboard')
                ->assertOk()
                ->assertSee('Core Value Dashboard');
        }
    }

    public function test_disallowed_roles_receive_forbidden(): void
    {
        foreach (['employee', 'it_admin', 'supervisor'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/analytics/core-value-dashboard')
                ->assertForbidden();
        }
    }

    public function test_active_period_is_selected_by_default_and_averages_are_database_driven(): void
    {
        $user = User::factory()->create(['role' => 'admin_hr']);
        $department = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $activePeriod = $this->period('Active Period', 'active', '2026-06-17');
        $closedPeriod = $this->period('Closed Period', 'closed', '2026-01-01');

        $this->assessmentResult($activePeriod, $this->employee($department, 'EMP-CV-001', 'A One'), [
            'amanah_score' => 4.00,
            'kompeten_score' => 3.00,
            'harmonis_score' => 3.50,
            'loyal_score' => 4.50,
            'adaptif_score' => 2.50,
            'kolaboratif_score' => 5.00,
            'final_score' => 3.75,
        ]);
        $this->assessmentResult($activePeriod, $this->employee($department, 'EMP-CV-002', 'A Two'), [
            'amanah_score' => 5.00,
            'kompeten_score' => 4.00,
            'harmonis_score' => 3.50,
            'loyal_score' => 3.50,
            'adaptif_score' => 3.50,
            'kolaboratif_score' => 4.00,
            'final_score' => 4.25,
        ]);
        $this->assessmentResult($closedPeriod, $this->employee($department, 'EMP-CV-003', 'Closed Person'), [
            'amanah_score' => 1.00,
            'kompeten_score' => 1.00,
            'harmonis_score' => 1.00,
            'loyal_score' => 1.00,
            'adaptif_score' => 1.00,
            'kolaboratif_score' => 1.00,
            'final_score' => 1.00,
        ]);

        $this->actingAs($user)
            ->get('/analytics/core-value-dashboard')
            ->assertOk()
            ->assertSee('Active Period')
            ->assertSee('Employees Analyzed')
            ->assertSee('2')
            ->assertSee('4.00')
            ->assertSee('Kolaboratif')
            ->assertSee('Adaptif')
            ->assertSee('Amanah')
            ->assertSee('Kompeten')
            ->assertSee('Harmonis')
            ->assertSee('Loyal')
            ->assertSee('coreValueBarChart')
            ->assertSee('coreValueRadarChart')
            ->assertDontSee('Closed Person');
    }

    public function test_period_and_department_filters_work(): void
    {
        $user = User::factory()->create(['role' => 'management']);
        $operations = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $finance = Department::create(['name' => 'Finance', 'code' => 'FIN']);
        $activePeriod = $this->period('Active Period', 'active', '2026-06-17');
        $closedPeriod = $this->period('Closed Period', 'closed', '2026-01-01');

        $this->assessmentResult($activePeriod, $this->employee($operations, 'EMP-OPS', 'Ops Person'), [
            'amanah_score' => 5.00,
            'kompeten_score' => 5.00,
            'harmonis_score' => 5.00,
            'loyal_score' => 5.00,
            'adaptif_score' => 5.00,
            'kolaboratif_score' => 5.00,
            'final_score' => 5.00,
        ]);
        $this->assessmentResult($activePeriod, $this->employee($finance, 'EMP-FIN', 'Fin Person'), [
            'amanah_score' => 2.00,
            'kompeten_score' => 2.00,
            'harmonis_score' => 2.00,
            'loyal_score' => 2.00,
            'adaptif_score' => 2.00,
            'kolaboratif_score' => 2.00,
            'final_score' => 2.00,
        ]);
        $this->assessmentResult($closedPeriod, $this->employee($operations, 'EMP-OLD', 'Old Person'), [
            'amanah_score' => 1.50,
            'kompeten_score' => 1.50,
            'harmonis_score' => 1.50,
            'loyal_score' => 1.50,
            'adaptif_score' => 1.50,
            'kolaboratif_score' => 1.50,
            'final_score' => 1.50,
        ]);

        $this->actingAs($user)
            ->get("/analytics/core-value-dashboard?period_id={$activePeriod->id}&department_id={$operations->id}")
            ->assertOk()
            ->assertSee('5.00')
            ->assertSee('Sangat Baik')
            ->assertDontSee('2.00');

        $this->actingAs($user)
            ->get("/analytics/core-value-dashboard?period_id={$closedPeriod->id}")
            ->assertOk()
            ->assertSee('1.50')
            ->assertSee('Sangat Kurang')
            ->assertDontSee('5.00');
    }

    public function test_empty_state_is_shown_when_selected_filter_has_no_data(): void
    {
        $user = User::factory()->create(['role' => 'admin_hr']);
        $emptyDepartment = Department::create(['name' => 'Corporate Strategy', 'code' => 'STR']);
        $period = $this->period('Active Period', 'active', '2026-06-17');

        $this->actingAs($user)
            ->get("/analytics/core-value-dashboard?period_id={$period->id}&department_id={$emptyDepartment->id}")
            ->assertOk()
            ->assertSee('No core value assessment data available for the selected filters.')
            ->assertDontSee('coreValueBarChart')
            ->assertDontSee('coreValueRadarChart');
    }

    private function period(string $name, string $status, string $startDate): AssessmentPeriod
    {
        return AssessmentPeriod::create([
            'name' => $name,
            'semester' => 'Semester 1',
            'year' => (int) substr($startDate, 0, 4),
            'start_date' => $startDate,
            'end_date' => '2026-06-30',
            'status' => $status,
            'threshold_score' => 3.00,
        ]);
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

    /**
     * @param array<string, float> $scores
     */
    private function assessmentResult(AssessmentPeriod $period, Employee $employee, array $scores): AssessmentResult
    {
        return AssessmentResult::create($scores + [
            'assessment_period_id' => $period->id,
            'employee_id' => $employee->id,
            'self_score' => $scores['final_score'],
            'others_score' => $scores['final_score'],
            'gap_score' => 0,
            'category' => 'Baik',
            'talent_mapping_category' => 'Solid Contributor',
        ]);
    }
}
