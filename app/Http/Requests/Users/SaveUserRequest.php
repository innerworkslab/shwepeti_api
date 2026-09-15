<?php

namespace App\Http\Requests\Users;

use App\Enums\UserRoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($this->input('id'))
                    ->whereNull('deleted_at'),
            ],
            'password' => [Rule::requiredIf(fn () => blank($this->input('id'))), 'nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in(UserRoleEnum::values())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
