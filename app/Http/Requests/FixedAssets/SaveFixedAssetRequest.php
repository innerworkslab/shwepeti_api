<?php

namespace App\Http\Requests\FixedAssets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFixedAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:fixed_assets,id'],
            'reference_no' => ['nullable', 'string', 'max:255', Rule::unique('fixed_assets', 'reference_no')->ignore($this->input('id'))],
            'asset_code' => ['required', 'string', 'max:255', Rule::unique('fixed_assets', 'asset_code')->ignore($this->input('id'))],
            'asset_category_id' => ['required', 'integer', 'exists:asset_categories,id'],
            'cashbook_id' => ['required', 'integer', 'exists:cashbooks,id'],
            'name' => ['required', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['required', 'date'],
            'purchase_amount' => ['required', 'numeric', 'gt:0'],
            'warranty_expiry_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'documents' => ['nullable', 'array'],
            'remark' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('asset_code')) {
            $this->merge([
                'asset_code' => strtoupper(str_replace(' ', '-', trim((string) $this->input('asset_code')))),
            ]);
        }

        if ($this->has('name')) {
            $this->merge([
                'name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name'))),
            ]);
        }
    }
}
