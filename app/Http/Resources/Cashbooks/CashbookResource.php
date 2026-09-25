<?php

namespace App\Http\Resources\Cashbooks;

use App\Http\Resources\Concerns\FormatsDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashbookResource extends JsonResource
{
    use FormatsDateTime;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'opening_balance' => $this->opening_balance,
            'current_balance' => $this->current_balance,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
