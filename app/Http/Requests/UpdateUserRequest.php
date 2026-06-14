<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'role_id' => ['sometimes', 'integer', Rule::in([
                Role::EMPLOYEE_ID,
                Role::IT_SUPPORT_ID,
                Role::ADMIN_ID,
            ])],
            'is_suspended' => ['sometimes', 'boolean'],
        ];
    }
}
