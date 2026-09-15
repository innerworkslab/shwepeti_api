<?php

namespace App\Http\Resources\Rooms;

use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\RoomCategories\RoomCategoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    use FormatsDateTime;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'room_category_id' => $this->room_category_id,
            'room_category' => RoomCategoryResource::make($this->whenLoaded('roomCategory')),
            'price' => $this->price,
            'status' => $this->status?->value,
            'room_beds' => RoomBedResource::collection($this->whenLoaded('beds')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'deleted_by' => $this->deleted_by,
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
            'deleted_at' => $this->dateTime($this->deleted_at),
        ];
    }
}
