<?php

namespace App\Services\Menus;

use App\Models\Item;
use Illuminate\Validation\Validator;

class MenuRecipeValidator
{
    public function __invoke(Validator $validator): void
    {
        $recipes = $validator->getData()['recipes'] ?? [];
        if (! is_array($recipes) || $validator->errors()->has('recipes')) {
            return;
        }

        $validRecipes = [];
        foreach ($recipes as $index => $recipe) {
            if (! is_array($recipe) || $validator->errors()->hasAny(["recipes.{$index}.item_id", "recipes.{$index}.unit_id"])) {
                continue;
            }
            $validRecipes[$index] = $recipe;
        }

        $items = Item::query()->with(['stockUnit', 'unitConversions.fromUnit', 'unitConversions.toUnit'])
            ->whereIn('id', array_column($validRecipes, 'item_id'))->get()->keyBy('id');

        foreach ($validRecipes as $index => $recipe) {
            $item = $items->get($recipe['item_id'] ?? null);
            if ($item !== null && ! $item->availableUnits()->contains('id', (int) ($recipe['unit_id'] ?? 0))) {
                $validator->errors()->add("recipes.{$index}.unit_id", 'Choose the item stock unit or an active unit connected to it by an active from/to conversion.');
            }
        }
    }
}
