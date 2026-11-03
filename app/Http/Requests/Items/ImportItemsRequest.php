<?php

namespace App\Http\Requests\Items;

use Illuminate\Foundation\Http\FormRequest;

class ImportItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'extensions:xlsx', 'mimes:xlsx', 'max:2048']];
    }
}
