<?php

namespace App\Http\Requests\Pos\Bookings;

use App\Enums\PaymentMethodEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBookingPaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }
}
