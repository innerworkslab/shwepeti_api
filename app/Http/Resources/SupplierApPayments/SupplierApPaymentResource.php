<?php

namespace App\Http\Resources\SupplierApPayments;

use App\Http\Resources\Cashbooks\CashbookResource;
use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\Suppliers\SupplierResource;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierApPaymentResource extends JsonResource
{
    use FormatsDateTime;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'supplier_id' => $this->supplier_id,
            'cashbook_id' => $this->cashbook_id,
            'amount' => $this->amount,
            'payment_date' => $this->dateTime($this->payment_date),
            'status' => $this->status->value,
            'remark' => $this->remark,
            'created_by' => $this->created_by,
            'supplier' => SupplierResource::make($this->whenLoaded('supplier')),
            'cashbook' => CashbookResource::make($this->whenLoaded('cashbook')),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
