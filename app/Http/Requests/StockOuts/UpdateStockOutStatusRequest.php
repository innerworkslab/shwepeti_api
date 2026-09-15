<?php

namespace App\Http\Requests\StockOuts;

use App\Enums\StockOutStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockOutStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                StockOutStatusEnum::Posted->value,
                StockOutStatusEnum::Cancelled->value,
            ])],
        ];
    }
}
