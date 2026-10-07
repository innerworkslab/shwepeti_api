<?php

namespace App\Http\Resources\Pos\Bookings;

use App\Http\Resources\Concerns\FormatsDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosBookingPaymentResource extends JsonResource
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
            'cashbook_id' => $this->cashbook_id,
            'payment_no' => $this->payment_no,
            'payment_type' => $this->payment_type?->value,
            'payment_method' => $this->payment_method?->value,
            'amount' => $this->amount,
            'paid_at' => $this->dateTime($this->paid_at),
            'note' => $this->note,
            'created_by' => $this->created_by,
            'created_at' => $this->dateTime($this->created_at),
        ];
    }
}
