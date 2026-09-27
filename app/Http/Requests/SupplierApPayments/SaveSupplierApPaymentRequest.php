<?php

namespace App\Http\Requests\SupplierApPayments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSupplierApPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:supplier_ap_payments,id'],
            'reference_no' => ['nullable', 'string', 'max:255', Rule::unique('supplier_ap_payments', 'reference_no')->ignore($this->input('id'))],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'cashbook_id' => ['required', 'integer', 'exists:cashbooks,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'remark' => ['nullable', 'string'],
        ];
    }
}
