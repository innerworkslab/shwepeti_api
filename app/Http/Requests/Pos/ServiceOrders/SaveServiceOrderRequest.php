<?php

namespace App\Http\Requests\Pos\ServiceOrders;

use App\Enums\ServiceOrderTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveServiceOrderRequest extends FormRequest
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
            'type' => ['required', Rule::in(ServiceOrderTypeEnum::values())],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', Rule::exists('items', 'id')->where('is_active', true)],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'tax_amount' => ['sometimes', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ];
    }
}
