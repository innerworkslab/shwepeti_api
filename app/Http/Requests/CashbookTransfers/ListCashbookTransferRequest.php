<?php

namespace App\Http\Requests\CashbookTransfers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCashbookTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'cashbook_id' => ['nullable', 'integer', 'exists:cashbooks,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', Rule::when($this->filled('date_from'), 'after_or_equal:date_from')],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
