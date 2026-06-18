<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\SsoLoginRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SsoAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'akhlak360.role_mapping.admin_hr_employee_numbers' => ['EMP001'],
            'akhlak360.role_mapping.management_employee_numbers' => ['EMP002'],
            'akhlak360.role_mapping.it_admin_employee_numbers' => ['EMP003'],
        ]);
    }

    public function test_employee_can_login_by_email_or_employee_number(): void
    {
        $employee = $this->employee('WORK001', 'worker@example.com');

        $this->post('/sso/login', $this->credentials($employee->email))
            ->assertRedirect('/employee/dashboard');
        $this->assertAuthenticated();
        $this->post('/logout');

        $this->post('/sso/login', $this->credentials('work001'))
            ->assertRedirect('/employee/dashboard');
        $this->assertAuthenticated();
    }

    public function test_unknown_inactive_and_invalid_code_fail_with_the_same_public_error(): void
    {
        $inactive = $this->employee('INACTIVE1', 'inactive@example.com', 'inactive');

        foreach ([
            ['identity' => 'missing@example.com', 'simulation_code' => 'PERSONAL-SSO-2026'],
            ['identity' => $inactive->email, 'simulation_code' => 'PERSONAL-SSO-2026'],
            ['identity' => $inactive->email, 'simulation_code' => 'WRONG'],
        ] as $credentials) {
            $this->from('/sso/login')
                ->post('/sso/login', $credentials)
                ->assertRedirect('/sso/login')
                ->assertSessionHasErrors(['identity' => SsoLoginRequest::GENERIC_ERROR]);
            $this->assertGuest();
        }
    }

    public function test_employee_without_generated_personal_code_cannot_login(): void
    {
        $employee = $this->employee('NOCODE1', 'no.code@example.com');
        $employee->forceFill([
            'sso_code_hash' => null,
            'sso_code_generated_at' => null,
        ])->save();

        $this->from('/sso/login')
            ->post('/sso/login', $this->credentials($employee->employee_number))
            ->assertRedirect('/sso/login')
            ->assertSessionHasErrors(['identity' => SsoLoginRequest::GENERIC_ERROR]);

        $this->assertGuest();
    }

    public function test_unlinked_employee_is_provisioned_and_audited(): void
    {
        $employee = $this->employee('NEW001', 'new.employee@example.com');

        $this->post('/sso/login', $this->credentials($employee->employee_number))
            ->assertRedirect('/employee/dashboard');

        $user = $employee->fresh()->user;
        $this->assertNotNull($user);
        $this->assertSame('New Employee', $user->name);
        $this->assertSame('new.employee@example.com', $user->email);
        $this->assertSame('employee:NEW001', $user->sso_id);
        $this->assertSame('simulated_personal_sso', $user->sso_provider);
        $this->assertNotNull($user->last_login_at);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'sso_login',
            'module' => 'authentication',
        ]);
    }

    public function test_email_less_provisioning_uses_unique_non_routable_address(): void
    {
        $first = $this->employee('NOEMAIL1', null);
        $second = $this->employee('NOEMAIL2', null);

        $this->post('/sso/login', $this->credentials($first->employee_number));
        $this->post('/logout');
        $this->post('/sso/login', $this->credentials($second->employee_number));

        $this->assertSame('noemail1@internal.akhlak360.invalid', $first->fresh()->user->email);
        $this->assertSame('noemail2@internal.akhlak360.invalid', $second->fresh()->user->email);
        $this->assertNotSame($first->fresh()->user->email, $second->fresh()->user->email);
    }

    public function test_existing_linked_user_is_safely_synchronized(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'role' => 'employee',
            'sso_provider' => null,
            'sso_id' => null,
            'last_login_at' => null,
        ]);
        $employee = $this->employee('EMP001', 'admin_hr@example.com', user: $user);

        $this->post('/sso/login', $this->credentials('EMP001'))
            ->assertRedirect('/admin/dashboard');

        $user->refresh();
        $this->assertSame('New Employee', $user->name);
        $this->assertSame('admin_hr@example.com', $user->email);
        $this->assertSame('admin_hr', $user->role);
        $this->assertSame('simulated_personal_sso', $user->sso_provider);
        $this->assertSame('employee:EMP001', $user->sso_id);
        $this->assertNotNull($user->last_login_at);
    }

    public function test_privileged_roles_override_supervisor_status(): void
    {
        foreach ([
            'EMP001' => ['/admin/dashboard', 'admin_hr'],
            'EMP002' => ['/management/dashboard', 'management'],
            'EMP003' => ['/it/dashboard', 'it_admin'],
        ] as $number => [$dashboard, $role]) {
            $manager = $this->employee($number, strtolower($number).'@example.com');
            $this->employee('SUB-'.$number, 'sub-'.strtolower($number).'@example.com', supervisor: $manager);

            $this->post('/sso/login', $this->credentials($number))->assertRedirect($dashboard);
            $this->assertSame($role, $manager->fresh()->user->role);
            $this->post('/logout');
        }
    }

    public function test_removing_privileged_mapping_changes_role_on_next_login(): void
    {
        $manager = $this->employee('EMP001', 'mapped@example.com');
        $this->employee('SUB001', 'subordinate@example.com', supervisor: $manager);

        $this->post('/sso/login', $this->credentials('EMP001'))->assertRedirect('/admin/dashboard');
        $this->post('/logout');

        config(['akhlak360.role_mapping.admin_hr_employee_numbers' => []]);

        $this->post('/sso/login', $this->credentials('EMP001'))->assertRedirect('/supervisor/dashboard');
        $this->assertSame('supervisor', $manager->fresh()->user->role);
    }

    public function test_email_conflict_is_generic_publicly_and_detailed_internally(): void
    {
        Log::spy();
        User::factory()->create(['email' => 'conflict@example.com']);
        $employee = $this->employee('CONFLICT1', 'conflict@example.com');

        $this->from('/sso/login')
            ->post('/sso/login', $this->credentials($employee->employee_number))
            ->assertRedirect('/sso/login')
            ->assertSessionHasErrors(['identity' => SsoLoginRequest::GENERIC_ERROR]);

        $this->assertNull($employee->fresh()->user_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'sso_login_failed',
            'module' => 'authentication',
        ]);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_inactive_linked_employee_is_logged_out_on_protected_access(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        $employee = $this->employee('INACTIVE2', 'linked.inactive@example.com', 'inactive', $user);

        $this->actingAs($user)
            ->get('/employee/dashboard')
            ->assertRedirect('/sso/login')
            ->assertSessionHasErrors(['identity' => SsoLoginRequest::GENERIC_ERROR]);

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'employment_status' => 'inactive']);
    }

    public function test_failed_attempts_are_rate_limited(): void
    {
        RateLimiter::clear('missing@example.com|127.0.0.1');

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->from('/sso/login')
                ->post('/sso/login', $this->credentials('missing@example.com'))
                ->assertRedirect('/sso/login')
                ->assertSessionHasErrors(['identity' => SsoLoginRequest::GENERIC_ERROR]);
        }

        $this->assertGuest();
    }

    private function credentials(string $identity): array
    {
        return [
            'identity' => $identity,
            'simulation_code' => 'PERSONAL-SSO-2026',
        ];
    }

    private function employee(
        string $number,
        ?string $email,
        string $status = 'active',
        ?User $user = null,
        ?Employee $supervisor = null
    ): Employee {
        $department = Department::firstOrCreate(
            ['code' => 'TEST'],
            ['name' => 'Test Department']
        );

        return Employee::create([
            'user_id' => $user?->id,
            'department_id' => $department->id,
            'employee_number' => $number,
            'name' => 'New Employee',
            'email' => $email,
            'supervisor_id' => $supervisor?->id,
            'employment_status' => $status,
            'sso_code_hash' => Hash::make('PERSONAL-SSO-2026'),
            'sso_code_generated_at' => now(),
        ]);
    }
}
