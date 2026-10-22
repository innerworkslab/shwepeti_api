<?php

namespace App\Http\Requests\Cashbooks;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashbookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cashbook_id' => ['required', 'integer', 'exists:cashbooks,id'],
            'remark' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
