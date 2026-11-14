<?php

namespace App\Http\Requests\Cashbooks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportCashbookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), 'after_or_equal:date_from')],
        ];
    }
}
