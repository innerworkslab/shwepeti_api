<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Unit;
use App\Services\Inventory\InventoryDocumentService;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $inventoryDocumentService = app(InventoryDocumentService::class);

        $menus = [
            [
                'category_code' => 'MAIN_DISHES',
                'name' => 'Chicken Fried Rice',
                'code' => 'CHICKEN_FRIED_RICE',
                'description' => 'Fried rice with chicken.',
                'price' => 4500,
                'recipes' => [
                    ['item_code' => 'CHICKEN', 'unit' => 'g', 'quantity' => 150, 'remark' => 'Chicken portion'],
                    ['item_code' => 'RICE', 'unit' => 'g', 'quantity' => 250, 'remark' => 'Cooked rice portion'],
                    ['item_code' => 'COOKING_OIL', 'unit' => 'ml', 'quantity' => 20, 'remark' => 'Cooking oil'],
                ],
            ],
            [
                'category_code' => 'MAIN_DISHES',
                'name' => 'Chicken Rice',
                'code' => 'CHICKEN_RICE',
                'description' => 'Steamed rice served with chicken.',
                'price' => 4000,
                'recipes' => [
                    ['item_code' => 'CHICKEN', 'unit' => 'g', 'quantity' => 180, 'remark' => 'Chicken portion'],
                    ['item_code' => 'RICE', 'unit' => 'g', 'quantity' => 220, 'remark' => 'Rice portion'],
                    ['item_code' => 'COOKING_OIL', 'unit' => 'ml', 'quantity' => 10, 'remark' => 'Cooking oil'],
                ],
            ],
            [
                'category_code' => 'BEVERAGES',
                'name' => 'Cola',
                'code' => 'COLA',
                'description' => 'Bottled cola.',
                'price' => 1500,
                'recipes' => [
                    ['item_code' => 'COLA_BOTTLE', 'unit' => 'bottle', 'quantity' => 1, 'remark' => 'One bottle'],
                ],
            ],
            [
                'category_code' => 'BEVERAGES',
                'name' => 'Drinking Water',
                'code' => 'DRINKING_WATER',
                'description' => 'Bottled drinking water.',
                'price' => 1000,
                'recipes' => [
                    ['item_code' => 'WATER_BOTTLE', 'unit' => 'bottle', 'quantity' => 1, 'remark' => 'One bottle'],
                ],
            ],
        ];

        foreach ($menus as $menuData) {
            $menu = Menu::query()->updateOrCreate([
                'code' => $menuData['code'],
            ], [
                'menu_category_id' => $this->categoryId($menuData['category_code']),
                'name' => $menuData['name'],
                'description' => $menuData['description'],
                'price' => $menuData['price'],
                'cost_price' => null,
                'image_url' => null,
                'is_available' => true,
                'is_active' => true,
            ]);

            $recipeKeys = collect($menuData['recipes'])
                ->map(fn (array $recipe): string => $this->itemId($recipe['item_code']).'-'.$this->unitId($recipe['unit']))
                ->all();

            $menu->recipes()
                ->get()
                ->each(function ($recipe) use ($recipeKeys): void {
                    if (! in_array($recipe->item_id.'-'.$recipe->unit_id, $recipeKeys, true)) {
                        $recipe->delete();
                    }
                });

            foreach ($menuData['recipes'] as $recipe) {
                $itemId = $this->itemId($recipe['item_code']);
                $unitId = $this->unitId($recipe['unit']);

                $menu->recipes()->updateOrCreate([
                    'item_id' => $itemId,
                    'unit_id' => $unitId,
                ], [
                    'quantity' => $recipe['quantity'],
                    'base_quantity' => $inventoryDocumentService->baseQuantity($itemId, $unitId, $recipe['quantity']),
                    'remark' => $recipe['remark'] ?? null,
                ]);
            }
        }
    }

    private function categoryId(string $code): int
    {
        return MenuCategory::query()->where('code', $code)->firstOrFail()->id;
    }

    private function itemId(string $code): int
    {
        return Item::query()->where('code', $code)->firstOrFail()->id;
    }

    private function unitId(string $symbol): int
    {
        return Unit::query()->where('symbol', $symbol)->firstOrFail()->id;
    }
}
