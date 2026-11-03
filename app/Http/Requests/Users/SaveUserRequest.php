<?php

namespace App\Http\Requests\Users;

use App\Enums\UserPortalAccessEnum;
use App\Enums\UserRoleEnum;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->exists('permissions') || $this->user()->can('users.manage_permissions');
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
            'portal_access' => ['sometimes', Rule::in(UserPortalAccessEnum::values())],
            'is_active' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['required', 'string', 'distinct',
                Rule::in(app(PermissionCatalog::class)->names()),
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }
}
