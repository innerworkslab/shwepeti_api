<?php

namespace App\Http\Resources\Pos\ServiceOrders;

use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\Pos\Bookings\PosBookingResource;
use App\Http\Resources\Pos\Rooms\PosRoomResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosServiceOrderResource extends JsonResource
{
    use FormatsDateTime;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'booking' => PosBookingResource::make($this->whenLoaded('booking')),
            'room_id' => $this->room_id,
            'room' => PosRoomResource::make($this->whenLoaded('room')),
            'order_no' => $this->order_no,
            'type' => $this->type?->value,
            'status' => $this->status?->value,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'note' => $this->note,
            'items' => PosServiceOrderItemResource::collection($this->whenLoaded('items')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
