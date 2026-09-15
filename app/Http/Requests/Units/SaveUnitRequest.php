<?php

namespace App\Http\Requests\Units;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUnitRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:units,id'],
            'unit_group_id' => ['required', 'integer', 'exists:unit_groups,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('units', 'name')
                    ->where('unit_group_id', $this->input('unit_group_id'))
                    ->ignore($this->input('id')),
            ],
            'symbol' => [
                'required',
                'string',
                'max:255',
                Rule::unique('units', 'symbol')
                    ->where('unit_group_id', $this->input('unit_group_id'))
                    ->ignore($this->input('id')),
            ],
            'is_base' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'symbol'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => preg_replace('/\s+/', ' ', trim((string) $this->input($field))),
                ]);
            }
        }
    }
}
