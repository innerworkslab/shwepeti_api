<?php

namespace App\Http\Resources\SupplierApLedgers;

use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\Suppliers\SupplierResource;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierApLedgerResource extends JsonResource
{
    use FormatsDateTime;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'transaction_type' => $this->transaction_type->value,
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'transaction_date' => $this->dateTime($this->transaction_date),
            'remark' => $this->remark,
            'created_by' => $this->created_by,
            'supplier' => SupplierResource::make($this->whenLoaded('supplier')),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'created_at' => $this->dateTime($this->created_at),
        ];
    }
}
