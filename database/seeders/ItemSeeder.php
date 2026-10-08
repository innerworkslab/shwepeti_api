<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemUnitConversion;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'category_slug' => 'meat',
                'name' => 'Chicken',
                'code' => 'CHICKEN',
                'sku' => 'RM-MEAT-CHICKEN',
                'stock_unit' => 'kg',
                'description' => 'Chicken raw material stock.',
                'min_stock' => 10,
                'price' => 12000,
                'conversions' => [
                    ['from' => 'kg', 'to' => 'g', 'factor' => 1000],
                ],
            ],
            [
                'category_slug' => 'rice-grain',
                'name' => 'Rice',
                'code' => 'RICE',
                'sku' => 'RM-RICE',
                'stock_unit' => 'kg',
                'description' => 'Rice and grain stock.',
                'min_stock' => 25,
                'price' => 2500,
                'conversions' => [
                    ['from' => 'kg', 'to' => 'g', 'factor' => 1000],
                    ['from' => 'box', 'to' => 'kg', 'factor' => 25],
                ],
            ],
            [
                'category_slug' => 'cooking-oil',
                'name' => 'Cooking Oil',
                'code' => 'COOKING_OIL',
                'sku' => 'RM-OIL-COOKING',
                'stock_unit' => 'L',
                'description' => 'Cooking oil stock.',
                'min_stock' => 10,
                'price' => 8000,
                'conversions' => [
                    ['from' => 'L', 'to' => 'ml', 'factor' => 1000],
                    ['from' => 'carton', 'to' => 'L', 'factor' => 12],
                ],
            ],
            [
                'category_slug' => 'soft-drink',
                'name' => 'Cola Bottle',
                'code' => 'COLA_BOTTLE',
                'sku' => 'BV-SOFT-COLA-BOTTLE',
                'stock_unit' => 'bottle',
                'description' => 'Soft drink bottle stock.',
                'min_stock' => 24,
                'price' => 900,
                'conversions' => [
                    ['from' => 'carton', 'to' => 'bottle', 'factor' => 24],
                ],
            ],
            [
                'category_slug' => 'water',
                'name' => 'Drinking Water Bottle',
                'code' => 'WATER_BOTTLE',
                'sku' => 'BV-WATER-BOTTLE',
                'stock_unit' => 'bottle',
                'description' => 'Drinking water bottle stock.',
                'min_stock' => 48,
                'price' => 500,
                'conversions' => [
                    ['from' => 'carton', 'to' => 'bottle', 'factor' => 24],
                ],
            ],
            [
                'category_slug' => 'tissue',
                'name' => 'Tissue Pack',
                'code' => 'TISSUE_PACK',
                'sku' => 'CON-TISSUE-PACK',
                'stock_unit' => 'pack',
                'description' => 'Tissue pack stock.',
                'min_stock' => 20,
                'price' => 1000,
                'conversions' => [
                    ['from' => 'carton', 'to' => 'pack', 'factor' => 50],
                ],
            ],
            [
                'category_slug' => 'cleaning',
                'name' => 'Detergent Bottle',
                'code' => 'DETERGENT_BOTTLE',
                'sku' => 'CON-CLEAN-DETERGENT-BOTTLE',
                'stock_unit' => 'bottle',
                'description' => 'Cleaning detergent bottle stock.',
                'min_stock' => 10,
                'price' => 3500,
                'conversions' => [
                    ['from' => 'carton', 'to' => 'bottle', 'factor' => 12],
                ],
            ],
            [
                'category_slug' => 'room-accessories',
                'name' => 'Toothbrush',
                'code' => 'TOOTHBRUSH',
                'sku' => 'ACC-ROOM-TOOTHBRUSH',
                'stock_unit' => 'pcs',
                'description' => 'Room guest toothbrush stock.',
                'min_stock' => 100,
                'price' => 300,
                'conversions' => [
                    ['from' => 'pack', 'to' => 'pcs', 'factor' => 12],
                    ['from' => 'box', 'to' => 'pcs', 'factor' => 144],
                ],
            ],
            [
                'category_slug' => 'amenities',
                'name' => 'Extra Towel',
                'code' => 'AMENITY_EXTRA_TOWEL',
                'sku' => 'AMN-TOWEL-EXTRA',
                'stock_unit' => 'pcs',
                'description' => 'Extra towel amenity service item.',
                'min_stock' => 20,
                'price' => 2000,
                'conversions' => [],
            ],
            [
                'category_slug' => 'amenities',
                'name' => 'Extra Blanket',
                'code' => 'AMENITY_EXTRA_BLANKET',
                'sku' => 'AMN-BLANKET-EXTRA',
                'stock_unit' => 'pcs',
                'description' => 'Extra blanket amenity service item.',
                'min_stock' => 10,
                'price' => 3000,
                'conversions' => [],
            ],
            [
                'category_slug' => 'amenities',
                'name' => 'Guest Amenity Kit',
                'code' => 'AMENITY_GUEST_KIT',
                'sku' => 'AMN-GUEST-KIT',
                'stock_unit' => 'pcs',
                'description' => 'Guest amenity kit service item.',
                'min_stock' => 50,
                'price' => 1500,
                'conversions' => [
                    ['from' => 'box', 'to' => 'pcs', 'factor' => 50],
                ],
            ],
            [
                'category_slug' => 'laundry',
                'name' => 'Laundry Shirt',
                'code' => 'LAUNDRY_SHIRT',
                'sku' => 'LND-SHIRT',
                'stock_unit' => 'pcs',
                'description' => 'Laundry service for shirt.',
                'min_stock' => 0,
                'price' => 1500,
                'conversions' => [],
            ],
            [
                'category_slug' => 'laundry',
                'name' => 'Laundry Pants',
                'code' => 'LAUNDRY_PANTS',
                'sku' => 'LND-PANTS',
                'stock_unit' => 'pcs',
                'description' => 'Laundry service for pants.',
                'min_stock' => 0,
                'price' => 2000,
                'conversions' => [],
            ],
            [
                'category_slug' => 'laundry',
                'name' => 'Laundry Dress',
                'code' => 'LAUNDRY_DRESS',
                'sku' => 'LND-DRESS',
                'stock_unit' => 'pcs',
                'description' => 'Laundry service for dress.',
                'min_stock' => 0,
                'price' => 2500,
                'conversions' => [],
            ],
        ];

        foreach ($items as $itemData) {
            $item = Item::query()->updateOrCreate([
                'code' => $itemData['code'],
            ], [
                'item_category_id' => $this->categoryId($itemData['category_slug']),
                'name' => $itemData['name'],
                'sku' => $itemData['sku'],
                'barcode' => null,
                'stock_unit_id' => $this->unitId($itemData['stock_unit']),
                'description' => $itemData['description'],
                'min_stock' => $itemData['min_stock'],
                'price' => $itemData['price'],
                'is_active' => true,
            ]);

            foreach ($itemData['conversions'] as $conversion) {
                ItemUnitConversion::query()->updateOrCreate([
                    'item_id' => $item->id,
                    'from_unit_id' => $this->unitId($conversion['from']),
                    'to_unit_id' => $this->unitId($conversion['to']),
                ], [
                    'conversion_factor' => $conversion['factor'],
                    'is_active' => true,
                ]);
            }

            $item->priceHistories()->updateOrCreate([
                'old_price' => null,
            ], [
                'new_price' => $itemData['price'],
                'changed_at' => now(),
                'changed_by' => null,
            ]);
        }
    }

    private function categoryId(string $slug): int
    {
        return ItemCategory::query()->where('slug', $slug)->firstOrFail()->id;
    }

    private function unitId(string $symbol): int
    {
        return Unit::query()->where('symbol', $symbol)->firstOrFail()->id;
    }
}
