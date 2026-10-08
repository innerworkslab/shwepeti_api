<?php

namespace App\Http\Resources\Pos\ServiceOrders;

use App\Http\Resources\Items\ItemResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosServiceOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_order_id' => $this->service_order_id,
            'item_id' => $this->item_id,
            'item' => ItemResource::make($this->whenLoaded('item')),
            'qty' => $this->qty,
            'unit_price' => $this->unit_price,
            'total' => $this->total,
            'note' => $this->note,
        ];
    }
}
