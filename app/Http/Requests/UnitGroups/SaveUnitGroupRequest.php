<?php

namespace App\Http\Requests\UnitGroups;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveUnitGroupRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:unit_groups,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('unit_groups', 'name')->ignore($this->input('id')),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('unit_groups', 'slug')->ignore($this->input('id')),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name'))),
            ]);
        }

        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge([
                'slug' => Str::slug((string) $this->input('name')),
            ]);
        }
    }
}
