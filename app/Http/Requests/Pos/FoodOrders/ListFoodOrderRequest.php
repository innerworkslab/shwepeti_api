<?php

namespace App\Http\Requests\Pos\FoodOrders;

use App\Enums\FoodOrderStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListFoodOrderRequest extends FormRequest
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
            'status' => ['nullable', Rule::in(FoodOrderStatusEnum::values())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
