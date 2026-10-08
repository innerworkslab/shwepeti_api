<?php

namespace App\Http\Resources\Pos\Bookings;

use App\Enums\FoodOrderStatusEnum;
use App\Enums\ServiceOrderStatusEnum;
use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\Pos\FoodOrders\PosFoodOrderResource;
use App\Http\Resources\Pos\Rooms\PosRoomResource;
use App\Http\Resources\Pos\ServiceOrders\PosServiceOrderResource;
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
        $foodOrderAmount = $this->foodOrderAmount();
        $serviceOrderAmount = $this->serviceOrderAmount();
        $orderAmount = $foodOrderAmount + $serviceOrderAmount;
        $grandTotalAmount = (float) $this->total_amount + $orderAmount;
        $balanceAmount = max(0, $grandTotalAmount - (float) $this->paid_amount);

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
            'room_total_amount' => $this->total_amount,
            'food_order_amount' => number_format($foodOrderAmount, 2, '.', ''),
            'service_order_amount' => number_format($serviceOrderAmount, 2, '.', ''),
            'order_amount' => number_format($orderAmount, 2, '.', ''),
            'grand_total_amount' => number_format($grandTotalAmount, 2, '.', ''),
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'balance_amount' => number_format($balanceAmount, 2, '.', ''),
            'payments' => PosBookingPaymentResource::collection($this->whenLoaded('payments')),
            'food_orders' => PosFoodOrderResource::collection($this->whenLoaded('foodOrders')),
            'service_orders' => PosServiceOrderResource::collection($this->whenLoaded('serviceOrders')),
            'note' => $this->note,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'checked_in_by' => $this->checked_in_by,
            'checked_out_by' => $this->checked_out_by,
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }

    private function foodOrderAmount(): float
    {
        if (! $this->resource->relationLoaded('foodOrders')) {
            return 0;
        }

        return (float) $this->foodOrders
            ->reject(fn ($foodOrder) => $foodOrder->status === FoodOrderStatusEnum::Cancelled)
            ->sum(fn ($foodOrder) => (float) $foodOrder->total_amount);
    }

    private function serviceOrderAmount(): float
    {
        if (! $this->resource->relationLoaded('serviceOrders')) {
            return 0;
        }

        return (float) $this->serviceOrders
            ->reject(fn ($serviceOrder) => $serviceOrder->status === ServiceOrderStatusEnum::Cancelled)
            ->sum(fn ($serviceOrder) => (float) $serviceOrder->total_amount);
    }
}
