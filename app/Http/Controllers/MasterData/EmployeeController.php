<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\PersonalSsoCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employment_status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $employees = Employee::query()
            ->with(['department', 'position', 'supervisor', 'user'])
            ->search($request->string('search')->toString())
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('employment_status'), fn ($query) => $query->where('employment_status', $request->employment_status))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('master-data.employees.index', [
            'employees' => $employees,
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('master-data.employees.create', [
            'employee' => new Employee(['employment_status' => 'active']),
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request, PersonalSsoCodeService $ssoCodes): Response
    {
        $data = $this->validateEmployee($request);

        $employee = Employee::create($data);
        $plainCode = $ssoCodes->generate($employee);

        $this->audit($request, 'create', "Created employee {$employee->employee_number} - {$employee->name}.");
        $this->audit($request, 'generate_sso_code', "Generated initial personal SSO code for employee {$employee->employee_number}.");
        return response()
            ->view('master-data.employees.sso-code', compact('employee', 'plainCode'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache');
    }

    public function edit(Employee $employee): View
    {
        return view('master-data.employees.edit', [
            'employee' => $employee,
            ...$this->formOptions($employee),
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $this->validateEmployee($request, $employee);

        $employee->update($data);

        $this->audit($request, 'update', "Updated employee {$employee->employee_number} - {$employee->name}.");

        return redirect()
            ->route('master-data.employees.index')
            ->with('success', 'Employee updated successfully.');
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $employee->update(['employment_status' => 'inactive']);

        $this->audit($request, 'deactivate', "Deactivated employee {$employee->employee_number} - {$employee->name}.");

        return redirect()
            ->route('master-data.employees.index')
            ->with('warning', 'Employee deactivated successfully.');
    }

    public function generateSsoCode(Request $request, Employee $employee, PersonalSsoCodeService $ssoCodes): Response|RedirectResponse
    {
        if ($employee->employment_status !== 'active') {
            return back()->with('warning', 'Kode SSO hanya dapat dibuat untuk karyawan aktif.');
        }

        $plainCode = $ssoCodes->generate($employee);

        $this->audit($request, 'reset_sso_code', "Generated a new personal SSO code for employee {$employee->employee_number}.");
        return response()
            ->view('master-data.employees.sso-code', compact('employee', 'plainCode'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache');
    }

    private function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'employee_number' => ['required', 'string', 'max:100', Rule::unique('employees', 'employee_number')->ignore($employee)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'supervisor_id' => [
                'nullable',
                'exists:employees,id',
                Rule::notIn($employee ? [$employee->id] : []),
            ],
            'employment_status' => ['required', Rule::in(['active', 'inactive'])],
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($employee)],
            'hris_external_id' => ['nullable', 'string', 'max:255'],
        ]);

        if (filled($data['supervisor_id'] ?? null)) {
            $supervisor = Employee::query()
                ->with('position')
                ->active()
                ->find($data['supervisor_id']);

            if (! $supervisor?->isSupervisorCandidate()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'supervisor_id' => 'Supervisor harus pegawai aktif dengan bawahan langsung atau level jabatan kepemimpinan yang diizinkan.',
                ]);
            }
        }

        return $data;
    }

    private function formOptions(?Employee $employee = null): array
    {
        return [
            'departments' => Department::active()->orderBy('name')->get(),
            'positions' => Position::orderBy('name')->get(),
            'supervisors' => Employee::active()
                ->supervisorCandidates()
                ->when($employee, fn ($query) => $query->whereKeyNot($employee->id))
                ->with('position')
                ->orderBy('name')
                ->get(),
            'users' => User::query()
                ->where(function ($query) use ($employee): void {
                    $query->whereDoesntHave('employee')
                        ->when(
                            $employee?->user_id,
                            fn ($query, $userId) => $query->orWhere('id', $userId)
                        );
                })
                ->orderBy('name')
                ->get(),
        ];
    }

    private function audit(Request $request, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module' => 'employees',
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
