<?php

namespace App\Http\Resources\Pos\FoodOrders;

use App\Http\Resources\Menus\MenuResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosFoodOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'food_order_id' => $this->food_order_id,
            'menu_id' => $this->menu_id,
            'menu' => MenuResource::make($this->whenLoaded('menu')),
            'qty' => $this->qty,
            'unit_price' => $this->unit_price,
            'total' => $this->total,
            'note' => $this->note,
        ];
    }
}
