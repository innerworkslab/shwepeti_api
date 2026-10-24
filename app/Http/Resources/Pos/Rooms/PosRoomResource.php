<?php

namespace App\Http\Resources\Pos\Rooms;

use App\Enums\BookingStatusEnum;
use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\Pos\Bookings\PosBookingResource;
use App\Http\Resources\RoomCategories\RoomCategoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosRoomResource extends JsonResource
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
            'bookings' => PosBookingResource::collection($this->whenLoaded('bookings')),
            'current_booking' => PosBookingResource::make(
                $this->whenLoaded(
                    'bookings',
                    fn() => $this->bookings
                        ->whereIn('status', [
                            BookingStatusEnum::Reserved,
                            BookingStatusEnum::CheckedIn,
                        ])
                        ->first()
                )
            ),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
