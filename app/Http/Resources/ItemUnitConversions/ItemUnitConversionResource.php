<?php

namespace App\Http\Resources\ItemUnitConversions;

use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\Units\UnitResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemUnitConversionResource extends JsonResource
{
    use FormatsDateTime;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'from_unit_id' => $this->from_unit_id,
            'to_unit_id' => $this->to_unit_id,
            'conversion_factor' => $this->conversion_factor,
            'is_active' => $this->is_active,
            'from_unit' => UnitResource::make($this->whenLoaded('fromUnit')),
            'to_unit' => UnitResource::make($this->whenLoaded('toUnit')),
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
