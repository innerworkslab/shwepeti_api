<?php

namespace App\Http\Requests\FixedAssets;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFixedAssetDepreciationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'depreciation_enabled' => ['required', 'boolean'],
            'depreciation_start_date' => ['exclude_unless:depreciation_enabled,true', 'required', 'date_format:Y-m-d'],
            'useful_life_months' => ['exclude_unless:depreciation_enabled,true', 'required', 'integer', 'min:1', 'max:1200'],
            'residual_value' => ['exclude_unless:depreciation_enabled,true', 'required', 'numeric', 'min:0', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
        ];
    }
}
