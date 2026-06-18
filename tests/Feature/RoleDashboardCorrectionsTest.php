<?php

namespace Tests\Feature;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentResponse;
use App\Models\AssessmentResult;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleDashboardCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_overdue_counts_pending_assignments_from_any_period_status(): void
    {
        Carbon::setTestNow('2026-06-18 10:00:00');
        $admin = User::factory()->create(['role' => 'admin_hr']);
        [$assessor, $assessee] = $this->employees();

        foreach (['draft', 'active', 'closed'] as $index => $status) {
            $period = $this->period("Past {$status}", $status, '2026-06-17', $index + 1);
            $this->assignment($period, $assessor, $assessee, 'pending', $index === 0 ? 'peer' : ($index === 1 ? 'supervisor' : 'subordinate'));
        }

        $future = $this->period('Future active', 'active', '2026-06-30', 10);
        $this->assignment($future, $assessor, $assessee, 'pending', 'self');
        $submittedPast = $this->period('Submitted past', 'closed', '2026-06-10', 11);
        $this->assignment($submittedPast, $assessor, $assessee, 'submitted', 'self');

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Overdue Semua Periode')
            ->assertSeeInOrder(['3', 'Overdue Semua Periode']);
    }

    public function test_active_users_uses_last_login_at_within_thirty_days(): void
    {
        Carbon::setTestNow('2026-06-18 10:00:00');
        $it = User::factory()->create([
            'role' => 'it_admin',
            'last_login_at' => now()->subDays(30),
        ]);
        User::factory()->create(['last_login_at' => now()->subDays(29)]);
        User::factory()->create(['last_login_at' => now()->subDays(30)->subSecond()]);
        User::factory()->create(['last_login_at' => null]);

        $this->actingAs($it)
            ->get('/it/dashboard')
            ->assertOk()
            ->assertSee('Pengguna Aktif 30 Hari')
            ->assertSeeInOrder(['2', 'Pengguna Aktif 30 Hari'])
            ->assertDontSee('Scheduler Running');
    }

    public function test_employee_dashboard_separates_assessor_tasks_from_personal_results(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        [$employee, $other] = $this->employees($user);
        $period = $this->period('Current', 'active', now()->addDays(5)->toDateString(), 1);
        $this->assignment($period, $employee, $other, 'pending', 'peer');
        AssessmentResult::create($this->resultAttributes($period, $employee, 4.1));

        $this->actingAs($user)
            ->get('/employee/dashboard')
            ->assertOk()
            ->assertSee('Tugas Saya sebagai Assessor')
            ->assertSee('Hasil Personal Saya sebagai Assessee')
            ->assertSee('Completed as Assessor')
            ->assertSee('Personal Final Score');
    }

    public function test_supervisor_only_sees_aggregate_direct_report_results_without_assessor_identity(): void
    {
        $supervisorUser = User::factory()->create(['role' => 'supervisor']);
        $department = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $supervisor = $this->employee($department, 'SUP-001', 'Supervisor Person', $supervisorUser);
        $direct = $this->employee($department, 'EMP-001', 'Direct Report', null, $supervisor->id);
        $outside = $this->employee($department, 'EMP-002', 'Outside Person');
        $peer = $this->employee($department, 'EMP-003', 'Secret Peer');
        $period = $this->period('Current', 'active', now()->addDays(5)->toDateString(), 1);
        $assignment = $this->assignment($period, $peer, $direct, 'submitted', 'peer');
        AssessmentResponse::create([
            'assessment_assignment_id' => $assignment->id,
            'core_value' => 'Amanah',
            'indicator' => 'Test',
            'score' => 5,
        ]);
        AssessmentResult::create($this->resultAttributes($period, $direct, 4.8));
        AssessmentResult::create($this->resultAttributes($period, $outside, 1.2));

        $this->actingAs($supervisorUser)
            ->get('/assessment/results')
            ->assertOk()
            ->assertSee('Aggregated Scores by Assessor Type')
            ->assertSee('5.00')
            ->assertDontSee('Secret Peer')
            ->assertDontSee('Direct Report')
            ->assertDontSee('Outside Person');
    }

    public function test_management_dashboard_attention_is_department_aggregate_only(): void
    {
        $management = User::factory()->create(['role' => 'management']);
        $department = Department::create(['name' => 'Finance', 'code' => 'FIN']);
        $employee = $this->employee($department, 'FIN-001', 'Confidential Employee');
        $period = $this->period('Current', 'active', now()->addDays(5)->toDateString(), 1);
        AssessmentResult::create($this->resultAttributes($period, $employee, 2.5));

        $this->actingAs($management)
            ->get("/management/dashboard?period_id={$period->id}")
            ->assertOk()
            ->assertSee('Management Attention by Department / Unit')
            ->assertSee('Finance')
            ->assertDontSee('Confidential Employee');
    }

    public function test_it_admin_has_export_history_but_not_report_creation(): void
    {
        $it = User::factory()->create(['role' => 'it_admin']);

        $this->actingAs($it)->get('/reports/history')->assertOk();
        $this->actingAs($it)->get('/reports/export')->assertForbidden();
        $this->actingAs($it)->get('/reports/export/csv')->assertForbidden();
    }

    private function employees(?User $firstUser = null): array
    {
        $department = Department::firstOrCreate(['code' => 'OPS'], ['name' => 'Operations']);

        return [
            $this->employee($department, 'EMP-A', 'Employee A', $firstUser),
            $this->employee($department, 'EMP-B', 'Employee B'),
        ];
    }

    private function employee(
        Department $department,
        string $number,
        string $name,
        ?User $user = null,
        ?int $supervisorId = null
    ): Employee {
        return Employee::create([
            'user_id' => $user?->id,
            'department_id' => $department->id,
            'employee_number' => $number,
            'name' => $name,
            'supervisor_id' => $supervisorId,
            'employment_status' => 'active',
        ]);
    }

    private function period(string $name, string $status, string $endDate, int $sequence): AssessmentPeriod
    {
        return AssessmentPeriod::create([
            'name' => $name,
            'semester' => "Semester {$sequence}",
            'year' => 2026,
            'start_date' => '2026-06-01',
            'end_date' => $endDate,
            'status' => $status,
            'threshold_score' => 3,
        ]);
    }

    private function assignment(
        AssessmentPeriod $period,
        Employee $assessor,
        Employee $assessee,
        string $status,
        string $type
    ): AssessmentAssignment {
        return AssessmentAssignment::create([
            'assessment_period_id' => $period->id,
            'assessor_employee_id' => $assessor->id,
            'assessee_employee_id' => $assessee->id,
            'assessor_type' => $type,
            'status' => $status,
            'submitted_at' => $status === 'submitted' ? now() : null,
        ]);
    }

    private function resultAttributes(AssessmentPeriod $period, Employee $employee, float $score): array
    {
        return [
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
        ];
    }
}
