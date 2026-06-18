<?php

namespace Tests\Feature;

use App\Models\AssessmentPeriod;
use App\Models\AssessmentResult;
use App\Models\Department;
use App\Models\Employee;
use App\Models\IdpRecommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsFunctionalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_analytics_pages_render_database_data_and_charts(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin_hr')->firstOrFail();

        $this->actingAs($admin)->get('/analytics/department-distribution')
            ->assertOk()
            ->assertSee('Distribusi Departemen')
            ->assertSee('Human Capital')
            ->assertSee('departmentAverageChart');

        $this->actingAs($admin)->get('/analytics/semester-trend')
            ->assertOk()
            ->assertSee('Semester 2 2025')
            ->assertSee('Semester 1 2026')
            ->assertSee('finalScoreTrendChart')
            ->assertSee('coreValueTrendChart');

        $this->actingAs($admin)->get('/analytics/below-threshold')
            ->assertOk()
            ->assertSee('Pegawai Di Bawah Threshold')
            ->assertSee('Core Value Terlemah')
            ->assertSee('Perlu Pengembangan');
    }

    public function test_department_and_period_filters_are_applied(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);
        $operations = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $finance = Department::create(['name' => 'Finance', 'code' => 'FIN']);
        $period = $this->period('Semester Uji', 'active', '2026-06-01');
        $opsEmployee = $this->employee($operations, 'OPS-01', 'Pegawai Operasi');
        $financeEmployee = $this->employee($finance, 'FIN-01', 'Pegawai Finance');
        $this->createResult($period, $opsEmployee, 2.50);
        $this->createResult($period, $financeEmployee, 4.20);

        $this->actingAs($admin)
            ->get("/analytics/department-distribution?period_id={$period->id}&department_id={$operations->id}")
            ->assertOk()
            ->assertSee('Operations')
            ->assertDontSee('Finance</td>', false);

        $this->actingAs($admin)
            ->get("/analytics/below-threshold?period_id={$period->id}&department_id={$operations->id}")
            ->assertOk()
            ->assertSee('Pegawai Operasi')
            ->assertDontSee('Pegawai Finance');
    }

    public function test_analytics_pages_have_safe_empty_states_and_validate_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);

        $this->actingAs($admin)->get('/analytics/department-distribution')
            ->assertOk()->assertSee('Belum ada periode penilaian');
        $this->actingAs($admin)->get('/analytics/semester-trend')
            ->assertOk()->assertSee('Belum ada periode penilaian');
        $this->actingAs($admin)->get('/analytics/below-threshold')
            ->assertOk()->assertSee('Belum ada periode penilaian');
        $this->actingAs($admin)->get('/analytics/department-distribution?period_id=999')
            ->assertSessionHasErrors('period_id');
    }

    public function test_only_authorized_roles_can_access_analytics_pages(): void
    {
        foreach (['/analytics/gap-analysis', '/analytics/department-distribution', '/analytics/semester-trend', '/analytics/below-threshold'] as $url) {
            $this->actingAs(User::factory()->create(['role' => 'employee']))
                ->get($url)
                ->assertForbidden();
        }
    }

    public function test_single_period_trend_explains_historical_limitation(): void
    {
        $management = User::factory()->create(['role' => 'management']);
        $department = Department::create(['name' => 'IT', 'code' => 'IT']);
        $period = $this->period('Satu Periode', 'active', '2026-06-01');
        $this->createResult($period, $this->employee($department, 'IT-01', 'Pegawai IT'), 3.75);

        $this->actingAs($management)->get('/analytics/semester-trend')
            ->assertOk()
            ->assertSee('Baru satu periode yang memiliki hasil');
    }

    private function period(string $name, string $status, string $start): AssessmentPeriod
    {
        return AssessmentPeriod::create([
            'name' => $name, 'semester' => 'Semester 1', 'year' => 2026,
            'start_date' => $start, 'end_date' => '2026-06-30',
            'status' => $status, 'threshold_score' => 3,
        ]);
    }

    private function employee(Department $department, string $number, string $name): Employee
    {
        return Employee::create([
            'department_id' => $department->id, 'employee_number' => $number,
            'name' => $name, 'employment_status' => 'active',
        ]);
    }

    private function createResult(AssessmentPeriod $period, Employee $employee, float $score): AssessmentResult
    {
        $result = AssessmentResult::create([
            'assessment_period_id' => $period->id, 'employee_id' => $employee->id,
            'amanah_score' => $score, 'kompeten_score' => $score + .1,
            'harmonis_score' => $score + .2, 'loyal_score' => $score + .3,
            'adaptif_score' => $score - .1, 'kolaboratif_score' => $score,
            'self_score' => $score + .2, 'others_score' => $score,
            'gap_score' => .2, 'final_score' => $score,
            'category' => $score < 3 ? 'Perlu Pengembangan' : 'Baik',
            'talent_mapping_category' => $score < 3 ? 'Need Development' : 'Solid Contributor',
        ]);

        if ($score < 3) {
            IdpRecommendation::create([
                'assessment_period_id' => $period->id, 'employee_id' => $employee->id,
                'weakest_core_value' => 'Adaptif', 'recommendation' => 'Coaching',
                'status' => 'draft',
            ]);
        }

        return $result;
    }
}
