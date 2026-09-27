<?php

namespace App\Http\Resources\SupplierApBalances;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierApBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $creditLimit = (float) $this->credit_limit_amount;

        return [
            'supplier_id' => $this->id,
            'supplier_code' => $this->supplier_code,
            'supplier_name' => $this->name,
            'supplier_type' => $this->supplier_type->value,
            'credit_limit_amount' => $this->credit_limit_amount,
            'payable_balance' => $this->payable_balance,
            'available_credit_amount' => $creditLimit > 0
                ? number_format(max(0, $creditLimit - (float) $this->payable_balance), 2, '.', '')
                : null,
            'is_active' => $this->is_active,
        ];
    }
}
