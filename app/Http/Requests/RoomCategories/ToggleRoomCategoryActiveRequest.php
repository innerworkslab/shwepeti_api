<?php

namespace App\Http\Requests\RoomCategories;

use Illuminate\Foundation\Http\FormRequest;

class ToggleRoomCategoryActiveRequest extends FormRequest
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
            'is_active' => ['required', 'boolean'],
        ];
    }
}
