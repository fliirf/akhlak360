<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SsoAuthenticationService
{
    public function __construct(private readonly RoleResolutionService $roles)
    {
    }

    public function authenticate(string $identity, string $simulationCode, Request $request): User
    {
        $employee = Employee::query()
            ->with('user')
            ->where(function ($query) use ($identity): void {
                $normalized = mb_strtolower(trim($identity));
                $query->whereRaw('LOWER(employee_number) = ?', [$normalized])
                    ->orWhereRaw('LOWER(email) = ?', [$normalized]);
            })
            ->first();

        if (! $employee) {
            Hash::check($simulationCode, Hash::make(Str::random(32)));
            $this->recordFailure($request, 'unknown_identity', ['identity' => $identity]);
            throw new RuntimeException('SSO authentication failed.');
        }

        if (! $employee->sso_code_hash || ! Hash::check($simulationCode, $employee->sso_code_hash)) {
            $this->recordFailure($request, 'invalid_personal_sso_code', [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
            ], $employee->user_id);
            throw new RuntimeException('SSO authentication failed.');
        }

        if ($employee->employment_status !== 'active') {
            $this->recordFailure($request, 'inactive_employee', [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
            ], $employee->user_id);
            throw new RuntimeException('SSO authentication failed.');
        }

        try {
            return DB::transaction(function () use ($employee, $request): User {
                $employee = Employee::query()->with('user')->lockForUpdate()->findOrFail($employee->id);
                $role = $this->roles->resolveRole($employee);
                $email = $this->resolvedEmail($employee);
                $conflictingUser = User::query()
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                    ->when($employee->user_id, fn ($query) => $query->whereKeyNot($employee->user_id))
                    ->first();

                if ($conflictingUser) {
                    throw new RuntimeException("Email {$email} is already assigned to user {$conflictingUser->id}.");
                }

                $user = $employee->user;

                if (! $user) {
                    $user = User::create([
                        'name' => $employee->name,
                        'email' => $email,
                        'email_verified_at' => now(),
                        'password' => Hash::make(Str::random(64)),
                        'role' => $role,
                        'sso_provider' => 'simulated_personal_sso',
                        'sso_id' => 'employee:'.$employee->employee_number,
                        'last_login_at' => now(),
                    ]);

                    $employee->update(['user_id' => $user->id]);
                } else {
                    $user->forceFill([
                        'name' => $employee->name,
                        'email' => $email,
                        'email_verified_at' => $user->email_verified_at ?? now(),
                        'role' => $role,
                        'sso_provider' => 'simulated_personal_sso',
                        'sso_id' => 'employee:'.$employee->employee_number,
                        'last_login_at' => now(),
                    ])->save();
                }

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'sso_login',
                    'module' => 'authentication',
                    'description' => "Successful Company SSO simulation login for employee {$employee->employee_number}.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return $user;
            });
        } catch (Throwable $exception) {
            $this->recordFailure($request, 'provisioning_conflict', [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'exception' => $exception->getMessage(),
            ], $employee->user_id);

            throw new RuntimeException('SSO authentication failed.', previous: $exception);
        }
    }

    public function dashboardPathForRole(string $role): string
    {
        return match ($role) {
            'admin_hr' => '/admin/dashboard',
            'supervisor' => '/supervisor/dashboard',
            'management' => '/management/dashboard',
            'it_admin' => '/it/dashboard',
            default => '/employee/dashboard',
        };
    }

    private function resolvedEmail(Employee $employee): string
    {
        if (filled($employee->email)) {
            return mb_strtolower(trim($employee->email));
        }

        return mb_strtolower(trim($employee->employee_number)).'@internal.akhlak360.invalid';
    }

    private function recordFailure(Request $request, string $reason, array $context, ?int $userId = null): void
    {
        Log::warning('Company SSO simulation authentication failed.', [
            'reason' => $reason,
            ...$context,
            'ip_address' => $request->ip(),
        ]);

        AuditLog::create([
            'user_id' => $userId,
            'action' => 'sso_login_failed',
            'module' => 'authentication',
            'description' => 'Internal SSO failure: '.$reason.'; '.json_encode($context),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
