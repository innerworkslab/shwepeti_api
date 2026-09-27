<?php

namespace App\Http\Requests\Suppliers;

use App\Enums\SupplierTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'supplier_code' => ['nullable', 'string', 'max:255', Rule::unique('suppliers', 'supplier_code')->ignore($this->input('id'))],
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'bank_account_no' => ['nullable', 'string', 'max:255'],
            'credit_limit_amount' => ['required', 'numeric', 'min:0'],
            'supplier_type' => ['required', Rule::enum(SupplierTypeEnum::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('supplier_code')) {
            $this->merge(['supplier_code' => strtoupper(trim((string) $this->input('supplier_code')))]);
        }

        if ($this->has('name')) {
            $this->merge(['name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name')))]);
        }
    }
}
