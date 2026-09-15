<?php

namespace App\Http\Requests\Rooms;

use App\Enums\RoomBedTypeEnum;
use App\Enums\RoomStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoomRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:rooms,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('rooms', 'name')
                    ->ignore($this->input('id'))
                    ->whereNull('deleted_at'),
            ],
            'room_category_id' => [
                'required',
                Rule::exists('room_categories', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
            ],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(RoomStatusEnum::values())],
            'room_beds' => ['required', 'array', 'min:1'],
            'room_beds.*.bed_type' => ['required', 'string', Rule::in(RoomBedTypeEnum::values()), 'distinct'],
            'room_beds.*.qty' => ['required', 'integer', 'min:1'],
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
