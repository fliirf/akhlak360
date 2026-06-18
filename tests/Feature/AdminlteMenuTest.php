<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminlteMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_hr_sees_admin_menu_groups(): void
    {
        $user = User::factory()->create([
            'role' => 'admin_hr',
        ]);

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Master Data')
            ->assertSee('Departments')
            ->assertSee('Assessment Cycle')
            ->assertSee('Assign Assessors')
            ->assertSee('Core Value Dashboard')
            ->assertSee('Reports')
            ->assertSee('Audit &amp; Compliance', false)
            ->assertSee('Audit Logs')
            ->assertSee('Compliance Monitoring')
            ->assertDontSee('System Settings');
    }

    public function test_employee_sees_assessment_and_personal_items_only(): void
    {
        $user = User::factory()->create([
            'role' => 'employee',
        ]);

        $this->actingAs($user)
            ->get('/employee/dashboard')
            ->assertOk()
            ->assertSee('Assessment')
            ->assertSee('Pending Assessments')
            ->assertSee('IDP &amp; Talent', false)
            ->assertSee('Notifications')
            ->assertDontSee('Master Data')
            ->assertDontSee('Reports')
            ->assertDontSee('System Settings');
    }

    public function test_it_admin_sees_hris_audit_and_system_menu_items(): void
    {
        $user = User::factory()->create([
            'role' => 'it_admin',
        ]);

        $this->actingAs($user)
            ->get('/it/dashboard')
            ->assertOk()
            ->assertSee('Master Data')
            ->assertSee('HRIS Sync')
            ->assertDontSee('Employees')
            ->assertSee('Audit &amp; Compliance', false)
            ->assertSee('Audit Logs')
            ->assertSee('Compliance Monitoring')
            ->assertSee('System Settings')
            ->assertDontSee('Export Reports');
    }

    public function test_core_value_dashboard_menu_is_hidden_from_supervisor(): void
    {
        $user = User::factory()->create([
            'role' => 'supervisor',
        ]);

        $this->actingAs($user)
            ->get('/supervisor/dashboard')
            ->assertOk()
            ->assertDontSee('Analytics')
            ->assertSee('Team Results')
            ->assertSee('Team IDP')
            ->assertDontSee('Core Value Dashboard');
    }

    public function test_management_sees_core_value_dashboard_menu(): void
    {
        $user = User::factory()->create([
            'role' => 'management',
        ]);

        $this->actingAs($user)
            ->get('/management/dashboard')
            ->assertOk()
            ->assertSee('Analytics')
            ->assertSee('Core Value Dashboard');
    }
}
