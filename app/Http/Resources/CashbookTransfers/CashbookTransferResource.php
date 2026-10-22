<?php

namespace App\Http\Resources\CashbookTransfers;

use App\Http\Resources\Cashbooks\CashbookResource;
use App\Http\Resources\CashbookTransactions\CashbookTransactionResource;
use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashbookTransferResource extends JsonResource
{
    use FormatsDateTime;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'request_id' => $this->request_id,
            'from_cashbook_id' => $this->from_cashbook_id,
            'to_cashbook_id' => $this->to_cashbook_id,
            'amount' => $this->amount,
            'transfer_date' => $this->dateTime($this->transfer_date),
            'remark' => $this->remark,
            'created_by' => $this->created_by,
            'from_cashbook' => CashbookResource::make($this->whenLoaded('fromCashbook')),
            'to_cashbook' => CashbookResource::make($this->whenLoaded('toCashbook')),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'transactions' => CashbookTransactionResource::collection($this->whenLoaded('transactions')),
            'created_at' => $this->dateTime($this->created_at),
        ];
    }
}
