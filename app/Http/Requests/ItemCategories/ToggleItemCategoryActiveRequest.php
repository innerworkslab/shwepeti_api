<?php

namespace App\Http\Requests\ItemCategories;

use Illuminate\Foundation\Http\FormRequest;

class ToggleItemCategoryActiveRequest extends FormRequest
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
