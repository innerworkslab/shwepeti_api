<?php

namespace Database\Seeders;

use App\Models\ItemCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ItemCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Raw Materials' => [
                'Meat',
                'Seafood',
                'Vegetable',
                'Rice & Grain',
                'Spice',
                'Cooking Oil',
            ],
            'Beverages' => [
                'Soft Drink',
                'Water',
                'Juice',
                'Coffee',
            ],
            'Consumables' => [
                'Tissue',
                'Takeaway',
                'Packaging',
                'Cleaning',
            ],
            'Accessories' => [
                'Room Accessories',
                'Kitchen Accessories',
                'Equipment Accessories',
            ],
        ];

        foreach ($categories as $parentName => $children) {
            $parent = ItemCategory::query()->updateOrCreate([
                'slug' => Str::slug($parentName),
            ], [
                'parent_id' => null,
                'name' => $parentName,
                'code' => $this->code($parentName),
                'description' => null,
                'is_active' => true,
            ]);

            foreach ($children as $childName) {
                ItemCategory::query()->updateOrCreate([
                    'slug' => Str::slug($childName),
                ], [
                    'parent_id' => $parent->id,
                    'name' => $childName,
                    'code' => $this->code($childName),
                    'description' => null,
                    'is_active' => true,
                ]);
            }
        }
    }

    private function code(string $name): string
    {
        return Str::of($name)
            ->replace('&', 'and')
            ->slug('_')
            ->upper()
            ->toString();
    }
}
