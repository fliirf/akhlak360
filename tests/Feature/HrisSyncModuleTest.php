<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\HrisSyncLog;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class HrisSyncModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_hris_sync_page_imports_csv_and_logs_activity(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);
        $csv = implode("\n", [
            'employee_number,name,email,department_code,department,position,position_level,supervisor_employee_number,employment_status,hris_external_id',
            'SUP-HRIS,Supervisor HRIS,supervisor.hris@example.com,OPS,Operations,Supervisor,L3,,active,EXT-SUP',
            'EMP-HRIS,Employee HRIS,employee.hris@example.com,OPS,Operations,Staff,L1,SUP-HRIS,active,EXT-EMP',
        ]);

        $this->actingAs($admin)
            ->get('/master-data/hris-sync')
            ->assertOk()
            ->assertSee('HRIS Sync')
            ->assertSee('Import Employee CSV');

        $this->actingAs($admin)
            ->post('/master-data/hris-sync/import', [
                'csv_file' => UploadedFile::fake()->createWithContent('employees.csv', $csv),
            ])
            ->assertRedirect('/master-data/hris-sync')
            ->assertSessionHas('success');

        $department = Department::where('code', 'OPS')->firstOrFail();
        $position = Position::where('name', 'Staff')->firstOrFail();
        $supervisor = Employee::where('employee_number', 'SUP-HRIS')->firstOrFail();

        $this->assertDatabaseHas('employees', [
            'employee_number' => 'EMP-HRIS',
            'name' => 'Employee HRIS',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'supervisor_id' => $supervisor->id,
            'hris_external_id' => 'EXT-EMP',
        ]);
        $this->assertDatabaseHas('hris_sync_logs', [
            'sync_type' => 'import_csv',
            'status' => 'success',
            'total_records' => 2,
            'success_records' => 2,
            'failed_records' => 0,
            'synced_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'module' => 'hris_sync',
            'action' => 'import_csv',
        ]);
    }

    public function test_manual_sync_logs_activity(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);

        $this->actingAs($admin)
            ->post('/master-data/hris-sync/manual')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('hris_sync_logs', [
            'sync_type' => 'manual_sync',
            'status' => 'success',
            'synced_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'module' => 'hris_sync',
            'action' => 'manual_sync',
        ]);

        $it = User::factory()->create(['role' => 'it_admin']);
        $this->actingAs($it)
            ->post('/master-data/hris-sync/manual')
            ->assertForbidden();

        $this->actingAs($it)
            ->get('/master-data/hris-sync')
            ->assertOk()
            ->assertSee('monitoring-only')
            ->assertDontSee('Import Employee CSV')
            ->assertDontSee('Run manual HRIS sync simulation?');
    }

    public function test_sample_download_invalid_csv_and_order_independent_supervisor_mapping(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);

        $this->actingAs($admin)
            ->get('/master-data/hris-sync/sample')
            ->assertOk()
            ->assertDownload('akhlak360-hris-sample.csv');

        $this->actingAs($admin)
            ->post('/master-data/hris-sync/import', [
                'csv_file' => UploadedFile::fake()->createWithContent('invalid.csv', "name,department\nOnly Name,Operations"),
            ])
            ->assertSessionHasErrors('csv_file');

        $this->assertDatabaseHas('hris_sync_logs', [
            'sync_type' => 'import_csv',
            'status' => 'failed',
            'total_records' => 0,
        ]);

        $csv = implode("\n", [
            'employee_number,name,department,supervisor_employee_number',
            'EMP-LATE,Employee Before Supervisor,Operations,SUP-LATE',
            'SUP-LATE,Supervisor Later,Operations,',
        ]);

        $this->actingAs($admin)
            ->post('/master-data/hris-sync/import', [
                'csv_file' => UploadedFile::fake()->createWithContent('out-of-order.csv', $csv),
            ])
            ->assertRedirect('/master-data/hris-sync')
            ->assertSessionHas('success');

        $employee = Employee::where('employee_number', 'EMP-LATE')->firstOrFail();
        $supervisor = Employee::where('employee_number', 'SUP-LATE')->firstOrFail();
        $this->assertSame($supervisor->id, $employee->supervisor_id);
    }

    public function test_hris_history_filters_persist_through_pagination_and_reject_invalid_values(): void
    {
        $it = User::factory()->create(['role' => 'it_admin']);

        foreach (range(1, 12) as $index) {
            HrisSyncLog::create([
                'sync_type' => 'manual_sync',
                'status' => 'success',
                'message' => "Filtered sync {$index}",
                'synced_by' => $it->id,
            ]);
        }

        $this->actingAs($it)
            ->get('/master-data/hris-sync?search=Filtered&sync_type=manual_sync&status=success')
            ->assertOk()
            ->assertSee('Filtered sync')
            ->assertSee('search=Filtered')
            ->assertSee('sync_type=manual_sync')
            ->assertSee('status=success');

        $this->actingAs($it)
            ->get('/master-data/hris-sync?status=unknown')
            ->assertSessionHasErrors('status');
    }

    public function test_invalid_import_rolls_back_all_rows_and_existing_position_level_is_updated(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);
        Position::create(['name' => 'Staff', 'level' => 'OLD']);

        $invalidCsv = implode("\n", [
            'employee_number,name,department,position,position_level,supervisor_employee_number',
            'VALID-ROW,Valid Row,Operations,Staff,L2,',
            'BROKEN-ROW,Broken Row,Operations,Staff,L2,MISSING-SUPERVISOR',
        ]);

        $this->actingAs($admin)
            ->post('/master-data/hris-sync/import', [
                'csv_file' => UploadedFile::fake()->createWithContent('invalid-reference.csv', $invalidCsv),
            ])
            ->assertRedirect('/master-data/hris-sync')
            ->assertSessionHas('warning');

        $this->assertDatabaseMissing('employees', ['employee_number' => 'VALID-ROW']);
        $this->assertDatabaseMissing('employees', ['employee_number' => 'BROKEN-ROW']);
        $this->assertSame('OLD', Position::where('name', 'Staff')->value('level'));

        $validCsv = implode("\n", [
            'employee_number,name,department,position,position_level,supervisor_employee_number',
            'VALID-ROW,Valid Row,Operations,Staff,L2,',
        ]);

        $this->actingAs($admin)
            ->post('/master-data/hris-sync/import', [
                'csv_file' => UploadedFile::fake()->createWithContent('valid-position.csv', $validCsv),
            ])
            ->assertSessionHas('success');

        $this->assertSame('L2', Position::where('name', 'Staff')->value('level'));
    }
}
