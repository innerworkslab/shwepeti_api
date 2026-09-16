<?php

namespace App\Http\Resources\Items;

use App\Http\Resources\Units\UnitResource;
use Illuminate\Http\Request;

class ItemWarehouseBalanceResource extends ItemResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'balance_quantity' => $this->balance_quantity ?? '0.000000',
            'balance' => [
                'quantity' => $this->balance_quantity ?? '0.000000',
                'unit_id' => $this->stock_unit_id,
                'unit' => UnitResource::make($this->whenLoaded('stockUnit')),
            ],
        ];
    }
}
