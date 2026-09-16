<?php

namespace App\Http\Resources\ItemPriceHistories;

use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\Items\ItemResource;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemPriceHistoryResource extends JsonResource
{
    use FormatsDateTime;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'old_price' => $this->old_price,
            'new_price' => $this->new_price,
            'changed_at' => $this->dateTime($this->changed_at),
            'changed_by' => $this->changed_by,
            'item' => ItemResource::make($this->whenLoaded('item')),
            'changer' => UserResource::make($this->whenLoaded('changer')),
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
