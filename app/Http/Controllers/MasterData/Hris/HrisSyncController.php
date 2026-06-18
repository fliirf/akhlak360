<?php

namespace App\Http\Controllers\MasterData\Hris;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\HrisSyncLog;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrisSyncController extends Controller
{
    private const REQUIRED_HEADERS = ['employee_number', 'name', 'department'];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sync_type' => ['nullable', Rule::in(['import_csv', 'manual_sync'])],
            'status' => ['nullable', Rule::in(['success', 'failed'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $query = HrisSyncLog::query()
            ->with('syncedBy')
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where('message', 'like', '%'.$search.'%'))
            ->when($validated['sync_type'] ?? null, fn ($query, $type) => $query->where('sync_type', $type))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
        $latest = (clone $query)->latest()->first();

        return view('master-data.hris-sync.index', [
            'logs' => (clone $query)->latest()->paginate(10)->withQueryString(),
            'summary' => [
                'total' => (clone $query)->count(),
                'successful' => (clone $query)->successful()->count(),
                'failed' => (clone $query)->failed()->count(),
                'latest' => $latest?->created_at,
            ],
        ]);
    }

    public function sample(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'employee_number', 'name', 'email', 'department_code', 'department',
                'position', 'position_level', 'supervisor_employee_number',
                'employment_status', 'hris_external_id',
            ]);
            fputcsv($handle, ['SUP-001', 'Demo Supervisor', 'supervisor@example.com', 'OPS', 'Operations', 'Supervisor', 'L3', '', 'active', 'HRIS-SUP-001']);
            fputcsv($handle, ['EMP-001', 'Demo Employee', 'employee@example.com', 'OPS', 'Operations', 'Staff', 'L1', 'SUP-001', 'active', 'HRIS-EMP-001']);
            fclose($handle);
        }, 'akhlak360-hris-sample.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        try {
            $rows = $this->readCsv($request->file('csv_file')->getRealPath());
        } catch (\Throwable $exception) {
            $this->recordSync($request, 'failed', 0, 0, 0, $exception->getMessage());

            return back()->withInput()->withErrors(['csv_file' => $exception->getMessage()]);
        }

        $messages = [];
        $incomingNumbers = collect($rows)
            ->map(fn (array $row) => trim((string) Arr::get($row, 'employee_number', '')))
            ->filter();
        $duplicateNumbers = $incomingNumbers->duplicates()->unique();

        foreach ($rows as $index => $row) {
            try {
                $this->validateRow($row, $incomingNumbers, $duplicateNumbers);
            } catch (\Throwable $exception) {
                $messages[] = 'Row '.($index + 2).': '.$exception->getMessage();
            }
        }

        if ($messages !== []) {
            $message = implode(' | ', array_slice($messages, 0, 5));
            $this->recordSync($request, 'failed', count($rows), 0, count($rows), $message);

            return redirect()
                ->route('master-data.hris-sync.index')
                ->with('warning', $message);
        }

        try {
            DB::transaction(function () use ($rows): void {
                foreach ($rows as $row) {
                    $this->syncRow($row, false);
                }

                foreach ($rows as $row) {
                    $this->syncSupervisor($row);
                }
            });
        } catch (\Throwable $exception) {
            report($exception);
            $message = 'CSV import rolled back because one or more records could not be synchronized.';
            $this->recordSync($request, 'failed', count($rows), 0, count($rows), $message);

            return redirect()
                ->route('master-data.hris-sync.index')
                ->with('warning', $message);
        }

        $this->recordSync($request, 'success', count($rows), count($rows), 0, 'CSV import completed successfully.');

        return redirect()
            ->route('master-data.hris-sync.index')
            ->with('success', 'CSV import completed successfully.');
    }

    public function manualSync(Request $request): RedirectResponse
    {
        HrisSyncLog::create([
            'sync_type' => 'manual_sync',
            'status' => 'success',
            'total_records' => Employee::count(),
            'success_records' => Employee::count(),
            'failed_records' => 0,
            'message' => 'Manual HRIS sync simulation completed.',
            'synced_by' => $request->user()?->id,
        ]);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'manual_sync',
            'module' => 'hris_sync',
            'description' => 'Manual HRIS sync simulation completed.',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Manual HRIS sync simulation completed.');
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException('CSV file could not be opened.');
        }

        $headers = fgetcsv($handle);

        if (! $headers) {
            throw new \RuntimeException('CSV file is empty.');
        }

        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);

        if (count($headers) !== count(array_unique($headers))) {
            fclose($handle);
            throw new \RuntimeException('CSV contains duplicate column names.');
        }

        $missing = array_diff(self::REQUIRED_HEADERS, $headers);

        if ($missing !== []) {
            fclose($handle);
            throw new \RuntimeException('CSV is missing required columns: '.implode(', ', $missing).'.');
        }

        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $normalized = array_slice(array_pad($values, count($headers), null), 0, count($headers));
            $combined = array_combine($headers, $normalized);

            if ($combined === false) {
                fclose($handle);
                throw new \RuntimeException('CSV row structure is invalid.');
            }

            $rows[] = $combined;
        }

        fclose($handle);

        if ($rows === []) {
            throw new \RuntimeException('CSV does not contain any employee rows.');
        }

        return $rows;
    }

    private function syncRow(array $row, bool $mapSupervisor = true): void
    {
        $employeeNumber = trim((string) Arr::get($row, 'employee_number', ''));
        $name = trim((string) Arr::get($row, 'name', ''));
        $departmentName = trim((string) (Arr::get($row, 'department') ?: Arr::get($row, 'department_name', '')));
        $departmentCode = trim((string) Arr::get($row, 'department_code', ''));

        if ($employeeNumber === '' || $name === '' || $departmentName === '') {
            throw new \InvalidArgumentException('employee_number, name, and department are required.');
        }

        $departmentLookup = $departmentCode !== '' ? ['code' => $departmentCode] : ['name' => $departmentName];
        $department = Department::firstOrCreate($departmentLookup, [
            'code' => $departmentCode !== '' ? $departmentCode : null,
            'name' => $departmentName,
            'is_active' => true,
        ]);

        if ($department->name !== $departmentName) {
            $department->update(['name' => $departmentName, 'is_active' => true]);
        }

        $position = null;
        $positionName = trim((string) Arr::get($row, 'position', ''));

        if ($positionName !== '') {
            $position = Position::updateOrCreate(
                ['name' => $positionName],
                ['level' => trim((string) Arr::get($row, 'position_level', '')) ?: null],
            );
        }

        Employee::updateOrCreate(
            ['employee_number' => $employeeNumber],
            [
                'department_id' => $department->id,
                'position_id' => $position?->id,
                ...($mapSupervisor ? ['supervisor_id' => $this->supervisorId($row, $employeeNumber)] : []),
                'name' => $name,
                'email' => trim((string) Arr::get($row, 'email', '')) ?: null,
                'employment_status' => in_array(Arr::get($row, 'employment_status'), ['active', 'inactive'], true)
                    ? Arr::get($row, 'employment_status')
                    : 'active',
                'hris_external_id' => trim((string) Arr::get($row, 'hris_external_id', '')) ?: null,
                'last_synced_at' => now(),
            ],
        );
    }

    private function validateRow(array $row, Collection $incomingNumbers, Collection $duplicateNumbers): void
    {
        $employeeNumber = trim((string) Arr::get($row, 'employee_number', ''));
        $name = trim((string) Arr::get($row, 'name', ''));
        $department = trim((string) (Arr::get($row, 'department') ?: Arr::get($row, 'department_name', '')));
        $status = trim((string) Arr::get($row, 'employment_status', ''));
        $supervisorNumber = trim((string) Arr::get($row, 'supervisor_employee_number', ''));

        if ($employeeNumber === '' || $name === '' || $department === '') {
            throw new \InvalidArgumentException('employee_number, name, and department are required.');
        }

        if ($duplicateNumbers->contains($employeeNumber)) {
            throw new \InvalidArgumentException("Duplicate employee_number {$employeeNumber} appears in the CSV.");
        }

        if ($status !== '' && ! in_array($status, ['active', 'inactive'], true)) {
            throw new \InvalidArgumentException('employment_status must be active or inactive.');
        }

        if ($supervisorNumber === $employeeNumber) {
            throw new \InvalidArgumentException('An employee cannot supervise themselves.');
        }

        if (
            $supervisorNumber !== ''
            && ! $incomingNumbers->contains($supervisorNumber)
            && ! Employee::where('employee_number', $supervisorNumber)->exists()
        ) {
            throw new \InvalidArgumentException("Supervisor {$supervisorNumber} was not found.");
        }
    }

    private function syncSupervisor(array $row): void
    {
        $employeeNumber = trim((string) Arr::get($row, 'employee_number', ''));
        Employee::where('employee_number', $employeeNumber)->update([
            'supervisor_id' => $this->supervisorId($row, $employeeNumber),
        ]);
    }

    private function supervisorId(array $row, string $employeeNumber): ?int
    {
        $supervisorNumber = trim((string) Arr::get($row, 'supervisor_employee_number', ''));

        if ($supervisorNumber === '') {
            return null;
        }

        if ($supervisorNumber === $employeeNumber) {
            throw new \InvalidArgumentException('An employee cannot supervise themselves.');
        }

        $supervisor = Employee::where('employee_number', $supervisorNumber)->first();

        if (! $supervisor) {
            throw new \InvalidArgumentException("Supervisor {$supervisorNumber} was not found.");
        }

        return $supervisor->id;
    }

    private function recordSync(Request $request, string $status, int $total, int $success, int $failed, string $message): void
    {
        HrisSyncLog::create([
            'sync_type' => 'import_csv',
            'status' => $status,
            'total_records' => $total,
            'success_records' => $success,
            'failed_records' => $failed,
            'message' => $message,
            'synced_by' => $request->user()?->id,
        ]);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'import_csv',
            'module' => 'hris_sync',
            'description' => "Imported HRIS CSV with {$success} success and {$failed} failed records.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
