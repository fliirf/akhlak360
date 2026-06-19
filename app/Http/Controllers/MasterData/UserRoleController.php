<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Services\RoleResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserRoleController extends Controller
{
    public function index(Request $request, RoleResolutionService $roles): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'effective_role' => ['nullable', Rule::in([...$roles->assignableRoles(), 'it_admin'])],
            'role_source' => ['nullable', Rule::in(['automatic', 'manual', 'protected_it_admin'])],
            'account_status' => ['nullable', Rule::in(['provisioned', 'unprovisioned', 'inactive'])],
        ]);

        $rows = Employee::query()
            ->with(['department', 'position', 'user'])
            ->withCount('subordinates')
            ->search($validated['search'] ?? null)
            ->when(
                isset($validated['department_id']),
                fn ($query) => $query->where('department_id', $validated['department_id'])
            )
            ->orderBy('name')
            ->get()
            ->map(function (Employee $employee) use ($roles): array {
                $automaticRole = $roles->resolveAutomaticRole($employee);
                $effectiveRole = $roles->resolveRole($employee);
                $source = $roles->source($employee);
                $accountStatus = $employee->employment_status !== 'active'
                    ? 'inactive'
                    : ($employee->user ? 'provisioned' : 'unprovisioned');

                return compact(
                    'employee',
                    'automaticRole',
                    'effectiveRole',
                    'source',
                    'accountStatus',
                );
            })
            ->when(
                isset($validated['effective_role']),
                fn ($rows) => $rows->where('effectiveRole', $validated['effective_role'])
            )
            ->when(
                isset($validated['role_source']),
                fn ($rows) => $rows->where('source', $validated['role_source'])
            )
            ->when(
                isset($validated['account_status']),
                fn ($rows) => $rows->where('accountStatus', $validated['account_status'])
            )
            ->values();

        $perPage = 10;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $employees = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return view('master-data.users.index', [
            'employees' => $employees,
            'departments' => Department::orderBy('name')->get(),
            'assignableRoles' => $roles->assignableRoles(),
            'accessMatrix' => config('akhlak360.role_access_matrix', []),
        ]);
    }

    public function update(
        UpdateUserRoleRequest $request,
        Employee $employee,
        RoleResolutionService $roles
    ): RedirectResponse {
        $this->ensureMutable($request, $employee, $roles, requireActive: true);
        $newRole = $request->validated('role');

        DB::transaction(function () use ($request, $employee, $roles, $newRole): void {
            $employee = Employee::query()->with('user')->lockForUpdate()->findOrFail($employee->id);
            $previousRole = $roles->resolveRole($employee);
            $previousSource = $roles->source($employee);

            $employee->update(['role_override' => $newRole]);
            $effectiveRole = $roles->resolveRole($employee->fresh());

            if ($employee->user) {
                $employee->user->update(['role' => $effectiveRole]);
                $this->invalidateTargetSessions($employee->user_id);
            }

            $this->audit(
                $request,
                'user_role_override_updated',
                "Role override for employee {$employee->employee_number} changed from {$previousRole} ({$previousSource}) to {$effectiveRole} (manual)."
            );
        });

        return back()->with(
            'success',
            "Role {$employee->name} berhasil diubah menjadi ".str($newRole)->replace('_', ' ')->title().'.'
        );
    }

    public function reset(
        Request $request,
        Employee $employee,
        RoleResolutionService $roles
    ): RedirectResponse {
        $this->ensureMutable($request, $employee, $roles);

        $automaticRole = DB::transaction(function () use ($request, $employee, $roles): string {
            $employee = Employee::query()->with('user')->lockForUpdate()->findOrFail($employee->id);
            $previousRole = $roles->resolveRole($employee);
            $previousSource = $roles->source($employee);

            $employee->update(['role_override' => null]);
            $employee->refresh();
            $automaticRole = $roles->resolveAutomaticRole($employee);

            if ($employee->user) {
                $employee->user->update(['role' => $automaticRole]);
                $this->invalidateTargetSessions($employee->user_id);
            }

            $this->audit(
                $request,
                'user_role_override_removed',
                "Role override for employee {$employee->employee_number} removed from {$previousRole} ({$previousSource}) to {$automaticRole} (automatic)."
            );

            return $automaticRole;
        });

        return back()->with(
            'success',
            "Override role {$employee->name} dihapus. Role otomatis: ".str($automaticRole)->replace('_', ' ')->title().'.'
        );
    }

    private function ensureMutable(
        Request $request,
        Employee $employee,
        RoleResolutionService $roles,
        bool $requireActive = false
    ): void {
        abort_unless($request->user()?->hasRole('admin_hr'), 403);
        abort_if((int) $employee->user_id === (int) $request->user()?->id, 403, 'You cannot change your own role.');
        abort_if($roles->isProtectedItAdmin($employee), 403, 'Protected IT Admin roles cannot be changed.');

        if ($requireActive) {
            abort_if($employee->employment_status !== 'active', 422, 'Inactive employees cannot receive a role override.');
        }
    }

    private function invalidateTargetSessions(?int $userId): void
    {
        if (! $userId || config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->delete();
    }

    private function audit(Request $request, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module' => 'user_roles',
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
