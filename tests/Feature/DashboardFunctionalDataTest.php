<?php

namespace Tests\Feature;

use App\Models\AssessmentResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFunctionalDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_seeded_role_dashboards_render_functional_sections(): void
    {
        $this->seed();

        $checks = [
            'admin_hr' => ['/admin/dashboard', ['Progress per Assessor Type', 'Recent Audit Logs']],
            'supervisor' => ['/supervisor/dashboard', ['Direct Report Assessment Status', 'Aggregated Score by Assessor Type']],
            'employee' => ['/employee/dashboard', ['Tugas Saya sebagai Assessor', 'Hasil Personal Saya sebagai Assessee']],
            'management' => ['/management/dashboard', ['Company Average', 'Semester Trend', 'Management Attention by Department']],
            'it_admin' => ['/it/dashboard', ['Pengguna Aktif 30 Hari', 'Latest Recorded Reminder Activity', 'Report Export History']],
        ];

        foreach ($checks as $role => [$url, $sections]) {
            $response = $this->actingAs(User::where('role', $role)->firstOrFail())->get($url)->assertOk();
            foreach ($sections as $section) {
                $response->assertSee($section);
            }
        }
    }

    public function test_unlinked_employee_and_supervisor_profiles_do_not_crash(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'employee']))
            ->get('/employee/dashboard')
            ->assertOk()
            ->assertSee('belum terhubung ke profil pegawai');

        $this->actingAs(User::factory()->create(['role' => 'supervisor']))
            ->get('/supervisor/dashboard')
            ->assertOk()
            ->assertSee('belum terhubung ke profil pegawai');
    }

    public function test_duplicate_placeholder_routes_redirect_to_functional_dashboards(): void
    {
        foreach ([
            'supervisor' => ['/supervisor/team', '/supervisor/dashboard'],
            'employee' => ['/employee/results', '/employee/dashboard'],
            'management' => ['/management/analytics', '/management/dashboard'],
        ] as $role => [$from, $to]) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get($from)
                ->assertRedirect($to);
        }
    }

    public function test_demo_seeder_contains_historical_and_varied_analytics_data(): void
    {
        $this->seed();

        $this->assertDatabaseCount('assessment_periods', 2);
        $this->assertDatabaseHas('assessment_periods', ['status' => 'closed']);
        $this->assertDatabaseHas('assessment_results', ['category' => 'Perlu Pengembangan']);
        $this->assertDatabaseHas('assessment_results', ['talent_mapping_category' => 'High Potential']);
        $this->assertDatabaseHas('assessment_results', ['talent_mapping_category' => 'Need Development']);
        $this->assertGreaterThanOrEqual(40, AssessmentResult::count());
        $this->assertGreaterThan(0, AssessmentResult::where('final_score', '<', 3)->count());
    }
}
