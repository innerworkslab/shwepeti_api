<?php

namespace App\Http\Requests\AssetCategories;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveAssetCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:asset_categories,id'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:asset_categories,id',
                Rule::notIn([(int) $this->input('id')]),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('asset_categories', 'slug')->ignore($this->input('id'))],
            'code' => ['required', 'string', 'max:255', Rule::unique('asset_categories', 'code')->ignore($this->input('id'))],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name'))),
            ]);
        }

        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge([
                'slug' => Str::slug((string) $this->input('name')),
            ]);
        }

        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper(str_replace([' ', '-'], '_', trim((string) $this->input('code')))),
            ]);
        }
    }
}
