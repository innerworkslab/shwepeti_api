<?php

namespace App\Http\Resources\CashbookTransactions;

use App\Http\Resources\Cashbooks\CashbookResource;
use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashbookTransactionResource extends JsonResource
{
    use FormatsDateTime;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'cashbook_id' => $this->cashbook_id,
            'transaction_type' => $this->transaction_type->value,
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'transaction_date' => $this->dateTime($this->transaction_date),
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'remark' => $this->remark,
            'created_by' => $this->created_by,
            'cashbook' => CashbookResource::make($this->whenLoaded('cashbook')),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
