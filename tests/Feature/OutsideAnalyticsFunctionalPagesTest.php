<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentResult;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutsideAnalyticsFunctionalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_settings_is_config_backed_and_it_admin_only(): void
    {
        $it = User::factory()->create(['role' => 'it_admin']);

        $this->actingAs($it)
            ->get('/system-settings')
            ->assertOk()
            ->assertSee('System Settings')
            ->assertSee('Default threshold')
            ->assertSee('3.00')
            ->assertSee('Interval reminder')
            ->assertSee('Bobot Penilaian Default')
            ->assertSee('Simulasi CSV');

        $this->actingAs(User::factory()->create(['role' => 'admin_hr']))
            ->get('/system-settings')
            ->assertForbidden();
    }

    public function test_assessment_results_are_scoped_to_employee_and_supervisor_team(): void
    {
        $fixture = $this->resultFixture();

        $this->actingAs($fixture['employeeUser'])
            ->get('/assessment/results')
            ->assertOk()
            ->assertSee('Hasil Penilaian')
            ->assertSee('Employee Result')
            ->assertDontSee('Outside Result');

        $this->actingAs($fixture['supervisorUser'])
            ->get('/assessment/results')
            ->assertOk()
            ->assertSee('Supervisor Result')
            ->assertSee('Employee Result')
            ->assertDontSee('Outside Result');
    }

    public function test_talent_mapping_enforces_role_scope_and_restricts_export(): void
    {
        $fixture = $this->resultFixture();

        $this->actingAs($fixture['employeeUser'])
            ->get('/idp-talent/talent-mapping')
            ->assertOk()
            ->assertSee('Employee Result')
            ->assertDontSee('Supervisor Result')
            ->assertDontSee('Outside Result')
            ->assertDontSee('Export CSV');

        $this->actingAs($fixture['supervisorUser'])
            ->get('/idp-talent/talent-mapping')
            ->assertOk()
            ->assertSee('Supervisor Result')
            ->assertSee('Employee Result')
            ->assertDontSee('Outside Result');

        $this->actingAs($fixture['employeeUser'])
            ->get('/idp-talent/talent-mapping/export')
            ->assertForbidden();
    }

    public function test_notifications_support_status_and_type_filters(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Unread Reminder',
            'message' => 'Complete assessment.',
            'type' => 'assessment_reminder',
        ]);
        AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Read Result',
            'message' => 'Result available.',
            'type' => 'result',
            'read_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/notifications?status=unread&type=assessment_reminder')
            ->assertOk()
            ->assertSee('Unread Reminder')
            ->assertDontSee('Read Result')
            ->assertSee('Total Notifikasi');

        $this->actingAs($user)
            ->get('/notifications?status=invalid')
            ->assertSessionHasErrors('status');
    }

    public function test_no_period_states_do_not_mix_or_crash(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);
        $employeeUser = User::factory()->create(['role' => 'employee']);
        $department = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        Employee::create([
            'user_id' => $employeeUser->id,
            'department_id' => $department->id,
            'employee_number' => 'EMPTY-001',
            'name' => 'Empty Employee',
            'employment_status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get('/audit-compliance/compliance-monitoring')
            ->assertOk()
            ->assertSee('Belum ada periode penilaian')
            ->assertSee('0%');

        $this->actingAs($employeeUser)
            ->get('/assessment/results')
            ->assertOk()
            ->assertSee('Belum ada periode penilaian');
    }

    public function test_legacy_placeholder_routes_redirect_to_canonical_pages(): void
    {
        $cases = [
            ['admin_hr', '/admin/master-data', '/master-data/employees'],
            ['admin_hr', '/admin/periods', '/assessment-cycle/periods'],
            ['admin_hr', '/admin/assignments', '/assessment-cycle/assign-assessors'],
            ['supervisor', '/supervisor/peer-approvals', '/assessment-cycle/peer-approval'],
            ['supervisor', '/supervisor/assessments', '/assessment/pending'],
            ['employee', '/employee/assessments', '/assessment/pending'],
            ['it_admin', '/it/hris-sync-logs', '/master-data/hris-sync'],
            ['it_admin', '/it/audit-logs', '/audit-compliance/audit-logs'],
            ['it_admin', '/it/settings', '/system-settings'],
        ];

        foreach ($cases as [$role, $from, $to]) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get($from)
                ->assertRedirect($to);
        }
    }

    public function test_profile_displays_linked_employee_information(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        $department = Department::create(['name' => 'Finance', 'code' => 'FIN']);
        $position = Position::create(['name' => 'Staff', 'level' => 'L1']);
        Employee::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => 'PROF-001',
            'name' => $user->name,
            'employment_status' => 'active',
            'hris_external_id' => 'HRIS-PROF-001',
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Profil Kepegawaian')
            ->assertSee('PROF-001')
            ->assertSee('Finance')
            ->assertSee('HRIS-PROF-001');
    }

    private function resultFixture(): array
    {
        $department = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $otherDepartment = Department::create(['name' => 'Finance', 'code' => 'FIN']);
        $period = AssessmentPeriod::create([
            'name' => 'Semester 1 2026',
            'semester' => 'Semester 1',
            'year' => 2026,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
            'threshold_score' => 3,
        ]);
        $supervisorUser = User::factory()->create(['role' => 'supervisor']);
        $employeeUser = User::factory()->create(['role' => 'employee']);
        $outsideUser = User::factory()->create(['role' => 'employee']);
        $supervisor = $this->employee($supervisorUser, $department, 'SUP-RES', 'Supervisor Result');
        $employee = $this->employee($employeeUser, $department, 'EMP-RES', 'Employee Result', $supervisor->id);
        $outside = $this->employee($outsideUser, $otherDepartment, 'OUT-RES', 'Outside Result');
        $this->createResult($period, $supervisor, 4.2);
        $this->createResult($period, $employee, 3.8);
        $this->createResult($period, $outside, 2.8);

        return compact('supervisorUser', 'employeeUser', 'outsideUser');
    }

    private function employee(User $user, Department $department, string $number, string $name, ?int $supervisorId = null): Employee
    {
        return Employee::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'employee_number' => $number,
            'name' => $name,
            'supervisor_id' => $supervisorId,
            'employment_status' => 'active',
        ]);
    }

    private function createResult(AssessmentPeriod $period, Employee $employee, float $score): void
    {
        AssessmentResult::create([
            'assessment_period_id' => $period->id,
            'employee_id' => $employee->id,
            'amanah_score' => $score,
            'kompeten_score' => $score,
            'harmonis_score' => $score,
            'loyal_score' => $score,
            'adaptif_score' => $score,
            'kolaboratif_score' => $score,
            'self_score' => $score,
            'others_score' => $score,
            'gap_score' => 0,
            'final_score' => $score,
            'category' => $score < 3 ? 'Perlu Pengembangan' : 'Baik',
            'talent_mapping_category' => $score < 3 ? 'Need Development' : 'Solid Contributor',
        ]);
    }
}
