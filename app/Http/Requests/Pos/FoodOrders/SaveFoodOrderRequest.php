<?php

namespace App\Http\Requests\Pos\FoodOrders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFoodOrderRequest extends FormRequest
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
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_id' => ['required', Rule::exists('menus', 'id')->where('is_active', true)->where('is_available', true)],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'tax_amount' => ['sometimes', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ];
    }
}
