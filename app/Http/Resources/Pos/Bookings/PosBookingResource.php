<?php

namespace App\Http\Resources\Pos\Bookings;

use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\Pos\Rooms\PosRoomResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosBookingResource extends JsonResource
{
    use FormatsDateTime;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_no' => $this->booking_no,
            'room_id' => $this->room_id,
            'booking_type' => $this->booking_type?->value,
            'charge_type' => $this->charge_type?->value,
            'room' => PosRoomResource::make($this->whenLoaded('room')),
            'guest_name' => $this->guest_name,
            'guest_phone' => $this->guest_phone,
            'guest_email' => $this->guest_email,
            'expected_check_in_at' => $this->dateTime($this->expected_check_in_at),
            'expected_check_out_at' => $this->dateTime($this->expected_check_out_at),
            'checked_in_at' => $this->dateTime($this->checked_in_at),
            'checked_out_at' => $this->dateTime($this->checked_out_at),
            'status' => $this->status?->value,
            'guest_count' => $this->guest_count,
            'room_rate' => $this->room_rate,
            'session_hours' => $this->session_hours,
            'session_rate' => $this->session_rate,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'balance_amount' => $this->balance_amount,
            'payments' => PosBookingPaymentResource::collection($this->whenLoaded('payments')),
            'note' => $this->note,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'checked_in_by' => $this->checked_in_by,
            'checked_out_by' => $this->checked_out_by,
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
