<?php

namespace App\Http\Resources\InventoryTransfers;

use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\Items\ItemResource;
use App\Http\Resources\Units\UnitResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryTransferItemResource extends JsonResource
{
    use FormatsDateTime;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_transfer_id' => $this->inventory_transfer_id,
            'item_id' => $this->item_id,
            'unit_id' => $this->unit_id,
            'quantity' => $this->quantity,
            'base_quantity' => $this->base_quantity,
            'batch_no' => $this->batch_no,
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'remark' => $this->remark,
            'item' => ItemResource::make($this->whenLoaded('item')),
            'unit' => UnitResource::make($this->whenLoaded('unit')),
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
