<?php

namespace App\Http\Requests\InventoryAdjustments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:inventory_adjustments,id'],
            'reference_no' => ['nullable', 'string', 'max:255', Rule::unique('inventory_adjustments', 'reference_no')->ignore($this->input('id'))],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'transaction_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'remark' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:inventory_adjustment_items,id'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.system_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.physical_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.remark' => ['nullable', 'string'],
        ];
    }
}
