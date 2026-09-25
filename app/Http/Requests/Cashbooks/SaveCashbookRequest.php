<?php

namespace App\Http\Requests\Cashbooks;

use App\Enums\CashbookTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCashbookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:cashbooks,id'],
            'code' => ['required', 'string', 'max:255', Rule::unique('cashbooks', 'code')->ignore($this->input('id'))],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CashbookTypeEnum::class)],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }

        if ($this->has('name')) {
            $this->merge(['name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name')))]);
        }
    }
}
