<?php

namespace App\Http\Resources\Items;

use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\ItemCategories\ItemCategoryResource;
use App\Http\Resources\ItemUnitConversions\ItemUnitConversionResource;
use App\Http\Resources\Units\UnitResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    use FormatsDateTime;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_category_id' => $this->item_category_id,
            'name' => $this->name,
            'code' => $this->code,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'stock_unit_id' => $this->stock_unit_id,
            'description' => $this->description,
            'min_stock' => $this->min_stock,
            'price' => $this->price,
            'is_active' => $this->is_active,
            'item_category' => ItemCategoryResource::make($this->whenLoaded('itemCategory')),
            'stock_unit' => UnitResource::make($this->whenLoaded('stockUnit')),
            'item_unit_conversions' => ItemUnitConversionResource::collection($this->whenLoaded('unitConversions')),
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
