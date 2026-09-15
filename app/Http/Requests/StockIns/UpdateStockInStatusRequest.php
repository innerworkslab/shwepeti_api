<?php

namespace App\Http\Requests\StockIns;

use App\Enums\StockInStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockInStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                StockInStatusEnum::Posted->value,
                StockInStatusEnum::Cancelled->value,
            ])],
        ];
    }
}
