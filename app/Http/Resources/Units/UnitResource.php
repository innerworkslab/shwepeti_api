<?php

namespace App\Http\Resources\Units;

use App\Http\Resources\UnitGroups\UnitGroupResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_group_id' => $this->unit_group_id,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'is_base' => $this->is_base,
            'is_active' => $this->is_active,
            'unit_group' => UnitGroupResource::make($this->whenLoaded('unitGroup')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
