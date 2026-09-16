<?php

namespace App\Http\Requests\Menus;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMenuRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:menus,id'],
            'menu_category_id' => ['required', 'integer', 'exists:menu_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('menus', 'code')->ignore($this->input('id')),
            ],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'is_available' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'recipes' => ['sometimes', 'array'],
            'recipes.*.id' => ['nullable', 'integer', 'exists:menu_recipes,id'],
            'recipes.*.item_id' => ['required_with:recipes', 'integer', 'exists:items,id'],
            'recipes.*.unit_id' => ['required_with:recipes', 'integer', 'exists:units,id'],
            'recipes.*.quantity' => ['required_with:recipes', 'numeric', 'gt:0'],
            'recipes.*.remark' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name'))),
            ]);
        }

        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper(str_replace([' ', '-'], '_', trim((string) $this->input('code')))),
            ]);
        }
    }
}
