<?php

namespace App\Http\Requests\InventoryAdjustments;

use App\Enums\AdjustmentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryAdjustmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                AdjustmentStatusEnum::Posted->value,
                AdjustmentStatusEnum::Cancelled->value,
            ])],
        ];
    }
}
