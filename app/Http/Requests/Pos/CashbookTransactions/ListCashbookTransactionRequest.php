<?php

namespace App\Http\Requests\Pos\CashbookTransactions;

use App\Enums\CashbookTransactionTypeEnum;
use App\Enums\CashbookTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCashbookTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'cashbook_id' => ['nullable', 'integer', Rule::exists('cashbooks', 'id')->whereIn('type', [
                CashbookTypeEnum::PosCash->value,
                CashbookTypeEnum::PosBank->value,
            ])],
            'transaction_type' => ['nullable', Rule::enum(CashbookTransactionTypeEnum::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', Rule::when($this->filled('date_from'), 'after_or_equal:date_from')],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
