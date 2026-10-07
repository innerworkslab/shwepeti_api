<?php

namespace App\Http\Requests\Pos\Bookings;

use App\Enums\BookingChargeTypeEnum;
use App\Enums\BookingStatusEnum;
use App\Enums\BookingTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'booking_type' => ['nullable', Rule::in(BookingTypeEnum::values())],
            'charge_type' => ['nullable', Rule::in(BookingChargeTypeEnum::values())],
            'status' => ['nullable', Rule::in(BookingStatusEnum::values())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
