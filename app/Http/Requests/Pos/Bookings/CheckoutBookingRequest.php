<?php

namespace App\Http\Requests\Pos\Bookings;

use App\Enums\PaymentMethodEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutBookingRequest extends FormRequest
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
            'cashbook_id' => ['required', Rule::exists('cashbooks', 'id')->where('is_active', true)],
            'payment_method' => ['required', Rule::in(PaymentMethodEnum::values())],
            'amount' => ['required', 'numeric', 'min:0'],
            'paid_at' => ['nullable', 'date'],
            'tax_percentage' => ['sometimes', 'numeric', 'between:0,100'],
            'tax_amount' => ['required_with:tax_percentage', 'numeric', 'min:0'],
            'service_charge_percentage' => ['sometimes', 'numeric', 'between:0,100'],
            'service_charge_amount' => ['required_with:service_charge_percentage', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ];
    }
}
