<?php

namespace App\Http\Requests\RoomCategories;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoomCategoryRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:room_categories,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('room_categories', 'name')
                    ->ignore($this->input('id'))
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name'))),
            ]);
        }
    }
}
