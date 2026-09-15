<?php

namespace App\Http\Resources\InventoryTransfers;

use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\Users\UserResource;
use App\Http\Resources\Warehouses\WarehouseResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryTransferResource extends JsonResource
{
    use FormatsDateTime;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'from_warehouse_id' => $this->from_warehouse_id,
            'to_warehouse_id' => $this->to_warehouse_id,
            'transaction_date' => $this->dateTime($this->transaction_date),
            'status' => $this->status?->value,
            'remark' => $this->remark,
            'created_by' => $this->created_by,
            'from_warehouse' => WarehouseResource::make($this->whenLoaded('fromWarehouse')),
            'to_warehouse' => WarehouseResource::make($this->whenLoaded('toWarehouse')),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'items' => InventoryTransferItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
