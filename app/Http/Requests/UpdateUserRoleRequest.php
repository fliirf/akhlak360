<?php

namespace App\Http\Requests;

use App\Services\RoleResolutionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin_hr') === true;
    }

    public function rules(): array
    {
        return [
            'role' => [
                'required',
                'string',
                Rule::in(RoleResolutionService::ASSIGNABLE_ROLES),
            ],
        ];
    }
}
