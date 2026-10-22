<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class HotelReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('date')) {
            return;
        }

        $today = today()->toDateString();
        $this->merge([
            'date_from' => $this->input('date_from') ?? $today,
            'date_to' => $this->input('date_to') ?? $today,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'required', 'date_format:Y-m-d', 'prohibits:date_from,date_to'],
            'date_from' => ['required_with:date_to', 'date_format:Y-m-d'],
            'date_to' => ['required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'room_id' => ['sometimes', 'integer', 'exists:rooms,id'],
            'room_category_id' => ['sometimes', 'integer', 'exists:room_categories,id'],
            'item_category_id' => ['sometimes', 'integer', 'exists:item_categories,id'],
            'warehouse_id' => ['sometimes', 'integer', 'exists:warehouses,id'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
