<?php

namespace App\Http\Resources\Suppliers;

use App\Http\Resources\Concerns\FormatsDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    use FormatsDateTime;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_code' => $this->supplier_code,
            'name' => $this->name,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'address' => $this->address,
            'bank_account_no' => $this->bank_account_no,
            'credit_limit_amount' => $this->credit_limit_amount,
            'payable_balance' => $this->payable_balance,
            'supplier_type' => $this->supplier_type->value,
            'is_active' => $this->is_active,
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
            'deleted_at' => $this->dateTime($this->deleted_at),
        ];
    }
}
