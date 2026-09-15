<?php

namespace App\Http\Resources\Units;

use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\UnitGroups\UnitGroupResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    use FormatsDateTime;

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
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
