<?php

namespace App\Services;

use App\Models\Employee;

class RoleResolutionService
{
    public const ASSIGNABLE_ROLES = [
        'admin_hr',
        'supervisor',
        'employee',
        'management',
    ];

    public function assignableRoles(): array
    {
        return self::ASSIGNABLE_ROLES;
    }

    public function resolveRole(Employee $employee): string
    {
        if ($this->isProtectedItAdmin($employee)) {
            return 'it_admin';
        }

        if (in_array($employee->role_override, self::ASSIGNABLE_ROLES, true)) {
            return $employee->role_override;
        }

        return $this->resolveAutomaticRole($employee);
    }

    public function resolveAutomaticRole(Employee $employee): string
    {
        foreach ([
            'it_admin_employee_numbers' => 'it_admin',
            'admin_hr_employee_numbers' => 'admin_hr',
            'management_employee_numbers' => 'management',
        ] as $configKey => $role) {
            if ($this->employeeNumberIsMapped($employee, $configKey)) {
                return $role;
            }
        }

        $hasSubordinates = $employee->relationLoaded('subordinates')
            ? $employee->subordinates->isNotEmpty()
            : (isset($employee->subordinates_count)
                ? (int) $employee->subordinates_count > 0
                : $employee->subordinates()->exists());

        return $hasSubordinates ? 'supervisor' : 'employee';
    }

    public function source(Employee $employee): string
    {
        if ($this->isProtectedItAdmin($employee)) {
            return 'protected_it_admin';
        }

        return in_array($employee->role_override, self::ASSIGNABLE_ROLES, true)
            ? 'manual'
            : 'automatic';
    }

    public function isProtectedItAdmin(Employee $employee): bool
    {
        return $this->employeeNumberIsMapped($employee, 'it_admin_employee_numbers');
    }

    private function employeeNumberIsMapped(Employee $employee, string $configKey): bool
    {
        $employeeNumber = mb_strtoupper(trim($employee->employee_number));
        $numbers = array_map(
            fn ($number) => mb_strtoupper(trim((string) $number)),
            config("akhlak360.role_mapping.{$configKey}", [])
        );

        return in_array($employeeNumber, $numbers, true);
    }
}
