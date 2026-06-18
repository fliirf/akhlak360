<?php

namespace Tests\Feature;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentPeriod;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuditComplianceModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logs_page_filters_by_user_module_action_and_date(): void
    {
        $it = User::factory()->create(['role' => 'it_admin']);
        $actor = User::factory()->create(['role' => 'admin_hr', 'name' => 'Audit Actor']);
        $reportLog = AuditLog::create([
            'user_id' => $actor->id,
            'module' => 'reports',
            'action' => 'export_csv',
            'description' => 'Report exported.',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
        ]);
        $reportLog->forceFill([
            'created_at' => '2026-06-17 09:00:00',
            'updated_at' => '2026-06-17 09:00:00',
        ])->save();
        AuditLog::create([
            'user_id' => $it->id,
            'module' => 'authentication',
            'action' => 'login',
            'description' => 'Login.',
            'ip_address' => '127.0.0.2',
            'user_agent' => 'test',
        ]);

        $this->actingAs($it)
            ->get("/audit-compliance/audit-logs?user_id={$actor->id}&module=reports&action=export_csv&date=2026-06-17")
            ->assertOk()
            ->assertSee('Audit Logs')
            ->assertSee('Audit Actor')
            ->assertSee('reports')
            ->assertSee('export_csv')
            ->assertSee('Report exported.')
            ->assertDontSee('Login.');
    }

    public function test_logout_is_audited(): void
    {
        $user = User::factory()->create(['role' => 'employee']);

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/sso/login');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'authentication',
            'action' => 'logout',
        ]);
    }

    public function test_compliance_monitoring_shows_completion_pending_and_overdue(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);
        $fixture = $this->fixture();
        $active = $fixture['activePeriod'];
        $closed = $fixture['closedPeriod'];

        AssessmentAssignment::create([
            'assessment_period_id' => $active->id,
            'assessor_employee_id' => $fixture['assessor']->id,
            'assessee_employee_id' => $fixture['assessee']->id,
            'assessor_type' => 'peer',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        AssessmentAssignment::create([
            'assessment_period_id' => $active->id,
            'assessor_employee_id' => $fixture['assessor']->id,
            'assessee_employee_id' => $fixture['assessee']->id,
            'assessor_type' => 'self',
            'status' => 'pending',
        ]);
        AssessmentAssignment::create([
            'assessment_period_id' => $closed->id,
            'assessor_employee_id' => $fixture['assessor']->id,
            'assessee_employee_id' => $fixture['assessee']->id,
            'assessor_type' => 'supervisor',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get("/audit-compliance/compliance-monitoring?period_id={$active->id}")
            ->assertOk()
            ->assertSee('Compliance Monitoring')
            ->assertSee('Total Assignments')
            ->assertSee('50%')
            ->assertSee('Pending Users')
            ->assertSee('Assessor Employee')
            ->assertDontSee('Send Reminders');

        $this->actingAs($admin)
            ->get("/audit-compliance/compliance-monitoring?period_id={$closed->id}")
            ->assertOk()
            ->assertSee('Overdue Assignments')
            ->assertSee('Closed Period');
    }

    public function test_only_it_admin_can_trigger_reminders_from_compliance_monitoring(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin_hr']);
        $it = User::factory()->create(['role' => 'it_admin']);

        $this->actingAs($admin)
            ->post('/audit-compliance/compliance-monitoring/reminders')
            ->assertForbidden();

        $this->actingAs($it)
            ->post('/audit-compliance/compliance-monitoring/reminders')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $it->id,
            'module' => 'compliance_monitoring',
            'action' => 'generate_reminders',
        ]);
    }

    private function fixture(): array
    {
        $department = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $assessorUser = User::factory()->create(['role' => 'employee', 'email' => 'assessor.audit@example.com']);
        $assessor = Employee::create([
            'user_id' => $assessorUser->id,
            'department_id' => $department->id,
            'employee_number' => 'AUD-001',
            'name' => 'Assessor Employee',
            'email' => $assessorUser->email,
            'employment_status' => 'active',
        ]);
        $assessee = Employee::create([
            'department_id' => $department->id,
            'employee_number' => 'AUD-002',
            'name' => 'Assessee Employee',
            'email' => 'assessee.audit@example.com',
            'employment_status' => 'active',
        ]);
        $activePeriod = AssessmentPeriod::create([
            'name' => 'Active Period',
            'semester' => 'Semester 1',
            'year' => 2026,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'status' => 'active',
            'threshold_score' => 3.00,
        ]);
        $closedPeriod = AssessmentPeriod::create([
            'name' => 'Closed Period',
            'semester' => 'Semester 2',
            'year' => 2025,
            'start_date' => now()->subDays(20)->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
            'status' => 'closed',
            'threshold_score' => 3.00,
        ]);

        return compact('department', 'assessor', 'assessee', 'activePeriod', 'closedPeriod');
    }
}
