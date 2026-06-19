<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'akhlak360.role_mapping.admin_hr_employee_numbers' => ['ADMIN-001'],
            'akhlak360.role_mapping.management_employee_numbers' => ['MGT-001'],
            'akhlak360.role_mapping.it_admin_employee_numbers' => ['IT-001'],
        ]);

        $this->department = Department::create([
            'name' => 'Human Capital',
            'code' => 'HC',
        ]);
    }

    public function test_only_admin_hr_can_open_user_role_management_and_menu_is_visible(): void
    {
        [$admin] = $this->admin();
        $this->employee('UNPROVISIONED', null);

        $this->actingAs($admin)
            ->get(route('master-data.users.index'))
            ->assertOk()
            ->assertSee('User &amp; Role Management', false)
            ->assertSee('UNPROVISIONED')
            ->assertSee('Belum diprovisi / Belum pernah login')
            ->assertSee('Ringkasan Hak Akses Role')
            ->assertSee('Internal / Protected');

        foreach (['supervisor', 'employee', 'management', 'it_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('master-data.users.index'))
                ->assertForbidden();
        }
    }

    public function test_admin_can_assign_each_supported_role_and_sync_existing_user(): void
    {
        [$admin] = $this->admin();
        $targetUser = User::factory()->create(['role' => 'employee']);
        $target = $this->employee('TARGET-001', $targetUser);

        foreach (['management', 'supervisor', 'employee', 'admin_hr'] as $role) {
            $this->actingAs($admin)
                ->patch(route('master-data.users.role.update', $target), ['role' => $role])
                ->assertRedirect();

            $this->assertSame($role, $target->fresh()->role_override);
            $this->assertSame($role, $targetUser->fresh()->role);
        }

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'user_role_override_updated',
            'module' => 'user_roles',
        ]);
    }

    public function test_override_can_be_set_before_provisioning_and_survives_sso_login(): void
    {
        [$admin] = $this->admin();
        $target = $this->employee('SSO-ROLE-001', null, ssoCode: 'ROLE-CODE');

        $this->actingAs($admin)
            ->patch(route('master-data.users.role.update', $target), ['role' => 'management'])
            ->assertRedirect();

        $this->post('/logout');
        $this->post('/sso/login', [
            'identity' => 'SSO-ROLE-001',
            'simulation_code' => 'ROLE-CODE',
        ])->assertRedirect('/management/dashboard');

        $this->assertSame('management', $target->fresh()->role_override);
        $this->assertSame('management', $target->fresh()->user->role);
    }

    public function test_reset_restores_supervisor_or_employee_automatic_role_and_audits(): void
    {
        [$admin] = $this->admin();
        $supervisorUser = User::factory()->create(['role' => 'management']);
        $supervisor = $this->employee('LEADER-001', $supervisorUser, override: 'management');
        $this->employee('REPORT-001', null, supervisor: $supervisor);
        $employeeUser = User::factory()->create(['role' => 'management']);
        $employee = $this->employee('STAFF-001', $employeeUser, override: 'management');

        $this->actingAs($admin)
            ->delete(route('master-data.users.role.reset', $supervisor))
            ->assertRedirect();
        $this->assertNull($supervisor->fresh()->role_override);
        $this->assertSame('supervisor', $supervisorUser->fresh()->role);

        $this->actingAs($admin)
            ->delete(route('master-data.users.role.reset', $employee))
            ->assertRedirect();
        $this->assertNull($employee->fresh()->role_override);
        $this->assertSame('employee', $employeeUser->fresh()->role);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'user_role_override_removed',
            'module' => 'user_roles',
        ]);
    }

    public function test_it_admin_self_inactive_and_manipulated_role_are_protected(): void
    {
        [$admin, $adminEmployee] = $this->admin();
        $itUser = User::factory()->create(['role' => 'it_admin']);
        $itEmployee = $this->employee('IT-001', $itUser);
        $inactive = $this->employee('INACTIVE-001', null, status: 'inactive');
        $target = $this->employee('TARGET-002', null);

        $this->actingAs($admin)
            ->patch(route('master-data.users.role.update', $adminEmployee), ['role' => 'employee'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('master-data.users.role.update', $itEmployee), ['role' => 'employee'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('master-data.users.role.update', $inactive), ['role' => 'management'])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->from(route('master-data.users.index'))
            ->patch(route('master-data.users.role.update', $target), ['role' => 'it_admin'])
            ->assertSessionHasErrors('role');

        $this->assertNull($adminEmployee->fresh()->role_override);
        $this->assertNull($itEmployee->fresh()->role_override);
        $this->assertNull($inactive->fresh()->role_override);
        $this->assertNull($target->fresh()->role_override);
    }

    public function test_effective_role_immediately_controls_middleware_gate_and_menu(): void
    {
        [$admin] = $this->admin();
        $targetUser = User::factory()->create(['role' => 'employee']);
        $target = $this->employee('TARGET-003', $targetUser);

        $this->actingAs($admin)
            ->patch(route('master-data.users.role.update', $target), ['role' => 'management']);

        $this->actingAs($targetUser->fresh())
            ->get('/management/dashboard')
            ->assertOk()
            ->assertSee('Analytics')
            ->assertSee('Core Value Dashboard')
            ->assertDontSee('Master Data');

        $this->actingAs($targetUser->fresh())
            ->get('/employee/dashboard')
            ->assertForbidden();
    }

    public function test_filters_cover_effective_role_source_and_account_status(): void
    {
        [$admin] = $this->admin();
        $manual = $this->employee('MANUAL-001', null, override: 'management');
        $this->employee('AUTO-001', User::factory()->create(['role' => 'employee']));
        $this->employee('IT-001', User::factory()->create(['role' => 'it_admin']));

        $this->actingAs($admin)
            ->get(route('master-data.users.index', [
                'effective_role' => 'management',
                'role_source' => 'manual',
                'account_status' => 'unprovisioned',
            ]))
            ->assertOk()
            ->assertSee($manual->employee_number)
            ->assertDontSee('AUTO-001')
            ->assertDontSee('IT-001');
    }

    private function admin(): array
    {
        $user = User::factory()->create(['role' => 'admin_hr']);
        $employee = $this->employee('ADMIN-001', $user);

        return [$user, $employee];
    }

    private function employee(
        string $number,
        ?User $user,
        string $status = 'active',
        ?string $override = null,
        ?Employee $supervisor = null,
        ?string $ssoCode = null,
    ): Employee {
        return Employee::create([
            'user_id' => $user?->id,
            'department_id' => $this->department->id,
            'employee_number' => $number,
            'name' => "Employee {$number}",
            'email' => strtolower($number).'@example.com',
            'supervisor_id' => $supervisor?->id,
            'employment_status' => $status,
            'role_override' => $override,
            'sso_code_hash' => $ssoCode ? Hash::make($ssoCode) : null,
            'sso_code_generated_at' => $ssoCode ? now() : null,
        ]);
    }
}
