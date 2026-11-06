<?php

namespace App\Http\Requests\Pos\CashbookTransactions;

use App\Enums\CashbookTypeEnum;
use App\Http\Requests\CashbookTransactions\SaveCashbookTransactionRequest as BaseSaveCashbookTransactionRequest;
use Illuminate\Validation\Rule;

class SaveCashbookTransactionRequest extends BaseSaveCashbookTransactionRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'cashbook_id' => ['required', 'integer', Rule::exists('cashbooks', 'id')->whereIn('type', [
                CashbookTypeEnum::PosCash->value,
                CashbookTypeEnum::PosBank->value,
            ])],
        ]);
    }
}
