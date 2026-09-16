<?php

namespace App\Http\Resources\Menus;

use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\MenuCategories\MenuCategoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    use FormatsDateTime;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_category_id' => $this->menu_category_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'price' => $this->price,
            'cost_price' => $this->cost_price,
            'image_url' => $this->image_url,
            'is_available' => $this->is_available,
            'is_active' => $this->is_active,
            'menu_category' => MenuCategoryResource::make($this->whenLoaded('menuCategory')),
            'recipes' => MenuRecipeResource::collection($this->whenLoaded('recipes')),
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
