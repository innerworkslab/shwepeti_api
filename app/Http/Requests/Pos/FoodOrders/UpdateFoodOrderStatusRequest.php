<?php

namespace App\Http\Requests\Pos\FoodOrders;

use App\Enums\FoodOrderStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFoodOrderStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(FoodOrderStatusEnum::values())],
        ];
    }
}
