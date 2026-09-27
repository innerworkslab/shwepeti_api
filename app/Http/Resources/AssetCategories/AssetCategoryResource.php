<?php

namespace App\Http\Resources\AssetCategories;

use App\Http\Resources\Concerns\FormatsDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetCategoryResource extends JsonResource
{
    use FormatsDateTime;

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
            'parent' => AssetCategoryResource::make($this->whenLoaded('parent')),
            'children' => AssetCategoryResource::collection($this->whenLoaded('children')),
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
