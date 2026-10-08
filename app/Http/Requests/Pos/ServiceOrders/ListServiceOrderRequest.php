<?php

namespace App\Http\Requests\Pos\ServiceOrders;

use App\Enums\ServiceOrderStatusEnum;
use App\Enums\ServiceOrderTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListServiceOrderRequest extends FormRequest
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
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'type' => ['nullable', Rule::in(ServiceOrderTypeEnum::values())],
            'status' => ['nullable', Rule::in(ServiceOrderStatusEnum::values())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
