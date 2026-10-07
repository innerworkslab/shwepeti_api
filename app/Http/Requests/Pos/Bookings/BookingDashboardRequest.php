<?php

namespace App\Http\Requests\Pos\Bookings;

use App\Enums\BookingStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingDashboardRequest extends FormRequest
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
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'room_category_id' => ['nullable', 'integer', 'exists:room_categories,id'],
            'status' => ['nullable', Rule::in(BookingStatusEnum::values())],
        ];
    }
}
