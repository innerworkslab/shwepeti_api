<?php

namespace App\Http\Requests\UnitGroups;

use Illuminate\Foundation\Http\FormRequest;

class ToggleUnitGroupActiveRequest extends FormRequest
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
            'is_active' => ['required', 'boolean'],
        ];
    }
}
