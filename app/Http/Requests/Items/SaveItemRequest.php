<?php

namespace App\Http\Requests\Items;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveItemRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:items,id'],
            'item_category_id' => ['required', 'integer', 'exists:item_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('items', 'code')->ignore($this->input('id')),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('items', 'sku')->ignore($this->input('id')),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('items', 'barcode')->ignore($this->input('id')),
            ],
            'stock_unit_id' => ['required', 'integer', 'exists:units,id'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'image' => ['nullable', 'image', 'max:2048'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'item_unit_conversions' => ['sometimes', 'array'],
            'item_unit_conversions.*.id' => ['nullable', 'integer', 'exists:item_unit_conversions,id'],
            'item_unit_conversions.*.from_unit_id' => ['required_with:item_unit_conversions', 'integer', 'exists:units,id'],
            'item_unit_conversions.*.to_unit_id' => ['required_with:item_unit_conversions', 'integer', 'exists:units,id', 'different:item_unit_conversions.*.from_unit_id'],
            'item_unit_conversions.*.conversion_factor' => ['required_with:item_unit_conversions', 'numeric', 'gt:0'],
            'item_unit_conversions.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeImageUrlUpload();

        if ($this->has('name')) {
            $this->merge([
                'name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name'))),
            ]);
        }

        foreach (['code', 'sku', 'barcode', 'image_url'] as $field) {
            if ($this->has($field) && filled($this->input($field))) {
                $this->merge([
                    $field => trim((string) $this->input($field)),
                ]);
            }
        }
    }

    private function normalizeImageUrlUpload(): void
    {
        if (! $this->hasFile('image_url')) {
            return;
        }

        if (! $this->hasFile('image')) {
            $this->files->set('image', $this->file('image_url'));
        }

        $this->files->remove('image_url');
        $this->convertedFiles = null;
    }
}
