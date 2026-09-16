<?php

namespace App\Http\Resources\Menus;

use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\Items\ItemResource;
use App\Http\Resources\Units\UnitResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuRecipeResource extends JsonResource
{
    use FormatsDateTime;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_id' => $this->menu_id,
            'item_id' => $this->item_id,
            'unit_id' => $this->unit_id,
            'quantity' => $this->quantity,
            'base_quantity' => $this->base_quantity,
            'remark' => $this->remark,
            'item' => ItemResource::make($this->whenLoaded('item')),
            'unit' => UnitResource::make($this->whenLoaded('unit')),
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
