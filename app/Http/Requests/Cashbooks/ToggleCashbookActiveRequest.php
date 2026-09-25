<?php

namespace App\Http\Requests\Cashbooks;

use Illuminate\Foundation\Http\FormRequest;

class ToggleCashbookActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
