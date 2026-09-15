<?php

namespace App\Http\Resources\Rooms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomBedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->room_id,
            'bed_type' => $this->bed_type?->value,
            'bed_type_label' => $this->bed_type?->label(),
            'qty' => $this->qty,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
