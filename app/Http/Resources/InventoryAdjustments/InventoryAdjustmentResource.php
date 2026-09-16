<?php

namespace App\Http\Resources\InventoryAdjustments;

use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\Users\UserResource;
use App\Http\Resources\Warehouses\WarehouseResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryAdjustmentResource extends JsonResource
{
    use FormatsDateTime;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'warehouse_id' => $this->warehouse_id,
            'transaction_date' => $this->dateTime($this->transaction_date),
            'status' => $this->status?->value,
            'reason' => $this->reason,
            'remark' => $this->remark,
            'created_by' => $this->created_by,
            'warehouse' => WarehouseResource::make($this->whenLoaded('warehouse')),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'items' => InventoryAdjustmentItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
