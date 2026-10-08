<?php

namespace App\Http\Requests\Pos\ServiceOrders;

use App\Enums\ServiceOrderStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(ServiceOrderStatusEnum::values())],
        ];
    }
}
