<?php

namespace App\Http\Requests\FixedAssets;

use App\Enums\FixedAssetStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFixedAssetStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                FixedAssetStatusEnum::Posted->value,
                FixedAssetStatusEnum::Cancelled->value,
            ])],
        ];
    }
}
