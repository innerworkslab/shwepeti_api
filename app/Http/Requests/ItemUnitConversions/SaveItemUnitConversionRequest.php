<?php

namespace App\Http\Requests\ItemUnitConversions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveItemUnitConversionRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:item_unit_conversions,id'],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'from_unit_id' => [
                'required',
                'integer',
                'exists:units,id',
                'different:to_unit_id',
                Rule::unique('item_unit_conversions', 'from_unit_id')
                    ->where('item_id', $this->input('item_id'))
                    ->where('to_unit_id', $this->input('to_unit_id'))
                    ->ignore($this->input('id')),
            ],
            'to_unit_id' => ['required', 'integer', 'exists:units,id'],
            'conversion_factor' => ['required', 'numeric', 'gt:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
