<?php

namespace App\Http\Requests\SupplierApPayments;

use App\Enums\SupplierApPaymentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierApPaymentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                SupplierApPaymentStatusEnum::Posted->value,
                SupplierApPaymentStatusEnum::Cancelled->value,
            ])],
        ];
    }
}
