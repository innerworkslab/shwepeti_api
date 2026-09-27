<?php

namespace App\Http\Requests\AssetCategories;

use Illuminate\Foundation\Http\FormRequest;

class ToggleAssetCategoryActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
