<?php

namespace App\Http\Requests\CashbookTransfers;

use Illuminate\Foundation\Http\FormRequest;

class SaveCashbookTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid', 'unique:cashbook_transfers,request_id'],
            'from_cashbook_id' => ['required', 'integer', 'exists:cashbooks,id'],
            'to_cashbook_id' => ['required', 'integer', 'different:from_cashbook_id', 'exists:cashbooks,id'],
            'amount' => ['required', 'numeric', 'regex:/^\d{1,16}(\.\d{1,2})?$/', 'gt:0'],
            'transfer_date' => ['required', 'date'],
            'remark' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('request_id'))) {
            $this->merge(['request_id' => strtolower($this->input('request_id'))]);
        }
    }
}
