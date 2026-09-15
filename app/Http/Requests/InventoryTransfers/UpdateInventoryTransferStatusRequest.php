<?php

namespace App\Http\Requests\InventoryTransfers;

use App\Enums\TransferStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryTransferStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                TransferStatusEnum::Posted->value,
                TransferStatusEnum::Cancelled->value,
            ])],
        ];
    }
}
