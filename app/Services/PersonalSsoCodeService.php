<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\Hash;

class PersonalSsoCodeService
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function generate(Employee $employee): string
    {
        $plainCode = collect(range(1, 3))
            ->map(fn () => $this->segment())
            ->implode('-');

        $employee->forceFill([
            'sso_code_hash' => Hash::make($plainCode),
            'sso_code_generated_at' => now(),
        ])->save();

        return $plainCode;
    }

    public function setKnownCode(Employee $employee, string $plainCode): void
    {
        $employee->forceFill([
            'sso_code_hash' => Hash::make($plainCode),
            'sso_code_generated_at' => now(),
        ])->save();
    }

    private function segment(): string
    {
        $segment = '';
        $maxIndex = strlen(self::ALPHABET) - 1;

        foreach (range(1, 4) as $_) {
            $segment .= self::ALPHABET[random_int(0, $maxIndex)];
        }

        return $segment;
    }
}
