<?php

namespace App\Http\Resources\InventoryLedgers;

use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\Items\ItemResource;
use App\Http\Resources\Units\UnitResource;
use App\Http\Resources\Users\UserResource;
use App\Http\Resources\Warehouses\WarehouseResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryLedgerResource extends JsonResource
{
    use FormatsDateTime;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'warehouse_id' => $this->warehouse_id,
            'transaction_type' => $this->transaction_type,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'quantity' => $this->quantity,
            'unit_id' => $this->unit_id,
            'base_quantity' => $this->base_quantity,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            'batch_no' => $this->batch_no,
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'balance_quantity' => $this->balance_quantity,
            'transaction_date' => $this->dateTime($this->transaction_date),
            'remark' => $this->remark,
            'created_by' => $this->created_by,
            'item' => ItemResource::make($this->whenLoaded('item')),
            'warehouse' => WarehouseResource::make($this->whenLoaded('warehouse')),
            'unit' => UnitResource::make($this->whenLoaded('unit')),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
