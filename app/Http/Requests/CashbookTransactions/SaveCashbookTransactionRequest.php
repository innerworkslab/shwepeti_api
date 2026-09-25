<?php

namespace App\Http\Requests\CashbookTransactions;

use App\Enums\CashbookTransactionTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCashbookTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_no' => ['nullable', 'string', 'max:255', 'unique:cashbook_transactions,reference_no'],
            'cashbook_id' => ['required', 'integer', 'exists:cashbooks,id'],
            'transaction_type' => ['required', Rule::enum(CashbookTransactionTypeEnum::class)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transaction_date' => ['required', 'date'],
            'remark' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('reference_no')) {
            $this->merge(['reference_no' => strtoupper(trim((string) $this->input('reference_no')))]);
        }
    }
}
