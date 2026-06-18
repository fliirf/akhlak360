<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SsoLoginRequest extends FormRequest
{
    public const GENERIC_ERROR = 'Identitas atau kode SSO tidak valid.';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identity' => ['required', 'string', 'max:255'],
            'simulation_code' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'identity.required' => self::GENERIC_ERROR,
            'simulation_code.required' => self::GENERIC_ERROR,
        ];
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'identity' => self::GENERIC_ERROR,
        ]);
    }

    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), 60);
    }

    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower(trim($this->string('identity')->toString())).'|'.$this->ip());
    }
}
