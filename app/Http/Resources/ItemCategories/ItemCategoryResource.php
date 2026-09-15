<?php

namespace App\Http\Resources\ItemCategories;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'parent' => ItemCategoryResource::make($this->whenLoaded('parent')),
            'children' => ItemCategoryResource::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
