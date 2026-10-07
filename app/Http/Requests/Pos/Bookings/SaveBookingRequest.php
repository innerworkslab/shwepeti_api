<?php

namespace App\Http\Requests\Pos\Bookings;

use App\Enums\BookingChargeTypeEnum;
use App\Enums\BookingTypeEnum;
use App\Enums\PaymentMethodEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBookingRequest extends FormRequest
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
            'room_id' => ['required', Rule::exists('rooms', 'id')->whereNull('deleted_at')],
            'booking_type' => ['sometimes', Rule::in(BookingTypeEnum::values())],
            'charge_type' => ['required', Rule::in(BookingChargeTypeEnum::values())],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:50'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'expected_check_in_at' => ['required', 'date'],
            'expected_check_out_at' => ['required', 'date', 'after:expected_check_in_at'],
            'session_hours' => ['required_if:charge_type,'.BookingChargeTypeEnum::Session->value, 'nullable', 'integer', 'min:1'],
            'session_rate' => ['required_if:charge_type,'.BookingChargeTypeEnum::Session->value, 'nullable', 'numeric', 'min:0'],
            'guest_count' => ['sometimes', 'integer', 'min:1'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'tax_amount' => ['sometimes', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
            'check_in_now' => ['sometimes', 'boolean'],
            'deposit' => ['nullable', 'array'],
            'deposit.cashbook_id' => ['required_with:deposit', Rule::exists('cashbooks', 'id')->where('is_active', true)],
            'deposit.amount' => ['required_with:deposit', 'numeric', 'min:0.01'],
            'deposit.payment_method' => ['required_with:deposit', Rule::in(PaymentMethodEnum::values())],
            'deposit.paid_at' => ['nullable', 'date'],
            'deposit.note' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'charge_type' => $this->input('charge_type', BookingChargeTypeEnum::Day->value),
        ]);

        if ($this->has('guest_name')) {
            $this->merge([
                'guest_name' => preg_replace('/\s+/', ' ', trim((string) $this->input('guest_name'))),
            ]);
        }
    }
}
