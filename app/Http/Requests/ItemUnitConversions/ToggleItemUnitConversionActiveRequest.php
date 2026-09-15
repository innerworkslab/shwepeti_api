<?php

namespace App\Http\Requests\ItemUnitConversions;

use Illuminate\Foundation\Http\FormRequest;

class ToggleItemUnitConversionActiveRequest extends FormRequest
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
