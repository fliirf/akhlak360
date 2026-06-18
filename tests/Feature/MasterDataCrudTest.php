<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterDataCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin_hr',
        ]);
    }

    public function test_department_crud_deactivates_when_related_employees_exist(): void
    {
        $admin = $this->admin();
        $department = Department::create(['code' => 'OPS', 'name' => 'Operations']);
        $position = Position::create(['name' => 'Staff', 'level' => 'L1']);

        Employee::create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => 'EMP-001',
            'name' => 'Demo Employee',
            'email' => 'demo.employee@example.com',
            'employment_status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get('/master-data/departments')
            ->assertOk()
            ->assertSee('Operations');

        $this->actingAs($admin)
            ->post('/master-data/departments', [
                'code' => 'HC',
                'name' => 'Human Capital',
            ])
            ->assertRedirect('/master-data/departments');

        $this->assertDatabaseHas('departments', ['code' => 'HC']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'departments', 'action' => 'create']);

        $this->actingAs($admin)
            ->delete("/master-data/departments/{$department->id}")
            ->assertRedirect('/master-data/departments');

        $this->assertFalse($department->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['module' => 'departments', 'action' => 'deactivate']);
    }

    public function test_position_crud_creates_updates_and_deletes_with_audit_logs(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/master-data/positions', [
                'name' => 'Supervisor',
                'level' => 'L3',
            ])
            ->assertRedirect('/master-data/positions');

        $position = Position::where('name', 'Supervisor')->firstOrFail();

        $this->actingAs($admin)
            ->put("/master-data/positions/{$position->id}", [
                'name' => 'Senior Supervisor',
                'level' => 'L3',
            ])
            ->assertRedirect('/master-data/positions');

        $this->actingAs($admin)
            ->delete("/master-data/positions/{$position->id}")
            ->assertRedirect('/master-data/positions');

        $this->assertDatabaseMissing('positions', ['id' => $position->id]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'positions', 'action' => 'create']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'positions', 'action' => 'update']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'positions', 'action' => 'delete']);
    }

    public function test_employee_crud_filters_updates_and_prevents_self_supervisor(): void
    {
        $admin = $this->admin();
        $department = Department::create(['code' => 'IT', 'name' => 'IT']);
        $position = Position::create(['name' => 'Staff', 'level' => 'L1']);
        $supervisorPosition = Position::create(['name' => 'Supervisor', 'level' => 'L3']);
        $linkedUser = User::factory()->create(['role' => 'employee']);

        $supervisor = Employee::create([
            'department_id' => $department->id,
            'position_id' => $supervisorPosition->id,
            'employee_number' => 'SUP-001',
            'name' => 'Supervisor Demo',
            'email' => 'supervisor.demo@example.com',
            'employment_status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post('/master-data/employees', [
                'employee_number' => 'EMP-100',
                'name' => 'Employee Demo',
                'email' => 'employee.demo@example.com',
                'department_id' => $department->id,
                'position_id' => $position->id,
                'supervisor_id' => $supervisor->id,
                'employment_status' => 'active',
                'user_id' => $linkedUser->id,
                'hris_external_id' => 'HRIS-EMP-100',
            ])
            ->assertOk()
            ->assertSee('Kode SSO Personal')
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');

        $employee = Employee::where('employee_number', 'EMP-100')->firstOrFail();

        $this->actingAs($admin)
            ->get("/master-data/employees/{$employee->id}/edit")
            ->assertOk()
            ->assertSee('Employee Demo')
            ->assertSee($linkedUser->name);

        $this->actingAs($admin)
            ->get('/master-data/employees?search=Employee+Demo&department_id='.$department->id.'&employment_status=active')
            ->assertOk()
            ->assertSee('Employee Demo');

        $this->actingAs($admin)
            ->put("/master-data/employees/{$employee->id}", [
                'employee_number' => 'EMP-100',
                'name' => 'Employee Demo Updated',
                'email' => 'employee.updated@example.com',
                'department_id' => $department->id,
                'position_id' => $position->id,
                'supervisor_id' => $employee->id,
                'employment_status' => 'active',
                'user_id' => $linkedUser->id,
                'hris_external_id' => 'HRIS-EMP-100',
            ])
            ->assertSessionHasErrors('supervisor_id');

        $this->actingAs($admin)
            ->delete("/master-data/employees/{$employee->id}")
            ->assertRedirect('/master-data/employees');

        $this->assertSame('inactive', $employee->fresh()->employment_status);
        $this->assertDatabaseHas('audit_logs', ['module' => 'employees', 'action' => 'create']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'employees', 'action' => 'deactivate']);
    }

    public function test_supervisor_options_and_validation_exclude_ordinary_staff(): void
    {
        $admin = $this->admin();
        $department = Department::create(['code' => 'ORG', 'name' => 'Organization']);
        $staffPosition = Position::create(['name' => 'Staff', 'level' => 'L1']);
        $leaderPosition = Position::create(['name' => 'Supervisor', 'level' => 'L3']);
        $ordinaryStaff = Employee::create([
            'department_id' => $department->id,
            'position_id' => $staffPosition->id,
            'employee_number' => 'STAFF-001',
            'name' => 'Ordinary Staff',
            'employment_status' => 'active',
        ]);
        $leader = Employee::create([
            'department_id' => $department->id,
            'position_id' => $leaderPosition->id,
            'employee_number' => 'LEADER-001',
            'name' => 'Leadership Candidate',
            'employment_status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get('/master-data/employees/create')
            ->assertOk()
            ->assertSee('LEADER-001 - Leadership Candidate')
            ->assertDontSee('STAFF-001 - Ordinary Staff');

        $this->actingAs($admin)
            ->post('/master-data/employees', [
                'employee_number' => 'NEW-STAFF',
                'name' => 'New Staff',
                'department_id' => $department->id,
                'position_id' => $staffPosition->id,
                'supervisor_id' => $ordinaryStaff->id,
                'employment_status' => 'active',
            ])
            ->assertSessionHasErrors('supervisor_id');

        $this->actingAs($admin)
            ->post('/master-data/employees', [
                'employee_number' => 'NEW-LEAD',
                'name' => 'New Team Member',
                'department_id' => $department->id,
                'position_id' => $staffPosition->id,
                'supervisor_id' => $leader->id,
                'employment_status' => 'active',
            ])
            ->assertOk()
            ->assertSee('Kode SSO Personal');
    }

    public function test_employee_search_remains_scoped_by_department_and_status(): void
    {
        $admin = $this->admin();
        $insideDepartment = Department::create(['code' => 'IN', 'name' => 'Inside']);
        $outsideDepartment = Department::create(['code' => 'OUT', 'name' => 'Outside']);

        Employee::create([
            'department_id' => $insideDepartment->id,
            'employee_number' => 'IN-001',
            'name' => 'Unrelated Active',
            'employment_status' => 'active',
        ]);
        Employee::create([
            'department_id' => $outsideDepartment->id,
            'employee_number' => 'OUT-001',
            'name' => 'Needle Outside',
            'employment_status' => 'active',
        ]);
        Employee::create([
            'department_id' => $insideDepartment->id,
            'employee_number' => 'IN-002',
            'name' => 'Needle Inactive',
            'employment_status' => 'inactive',
        ]);

        $this->actingAs($admin)
            ->get('/master-data/employees?search=Needle&department_id='.$insideDepartment->id.'&employment_status=active')
            ->assertOk()
            ->assertDontSee('Needle Outside')
            ->assertDontSee('Needle Inactive');
    }

    public function test_admin_can_generate_and_reset_a_personal_sso_code(): void
    {
        $admin = $this->admin();
        $department = Department::create(['code' => 'SSO', 'name' => 'SSO Test']);
        $employee = Employee::create([
            'department_id' => $department->id,
            'employee_number' => 'SSO-001',
            'name' => 'Personal SSO Employee',
            'email' => 'personal.sso@example.com',
            'employment_status' => 'active',
        ]);

        $firstResponse = $this->actingAs($admin)
            ->post("/master-data/employees/{$employee->id}/sso-code")
            ->assertOk()
            ->assertSee('Kode SSO Personal')
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
        preg_match('/<code[^>]*>([^<]+)<\/code>/', $firstResponse->getContent(), $firstMatch);
        $firstCode = trim(html_entity_decode($firstMatch[1]));

        $this->assertTrue(Hash::check($firstCode, $employee->fresh()->sso_code_hash));
        $this->assertNotNull($employee->fresh()->sso_code_generated_at);

        $secondResponse = $this->actingAs($admin)
            ->post("/master-data/employees/{$employee->id}/sso-code")
            ->assertOk()
            ->assertSee('Kode SSO Personal');
        preg_match('/<code[^>]*>([^<]+)<\/code>/', $secondResponse->getContent(), $secondMatch);
        $secondCode = trim(html_entity_decode($secondMatch[1]));

        $this->assertNotSame($firstCode, $secondCode);
        $this->assertFalse(Hash::check($firstCode, $employee->fresh()->sso_code_hash));
        $this->assertTrue(Hash::check($secondCode, $employee->fresh()->sso_code_hash));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'module' => 'employees',
            'action' => 'reset_sso_code',
        ]);
    }
}
