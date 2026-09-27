<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AssetCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Property' => [
                'Land',
                'Buildings',
                'Leasehold Improvements',
            ],
            'Furniture & Fixtures' => [
                'Guest Room Furniture',
                'Office Furniture',
                'Restaurant Furniture',
            ],
            'Equipment' => [
                'Kitchen Equipment',
                'Laundry Equipment',
                'Office Equipment',
                'IT Equipment',
                'Housekeeping Equipment',
            ],
            'Vehicles' => [
                'Cars',
                'Vans',
                'Motorcycles',
            ],
        ];

        foreach ($categories as $parentName => $children) {
            $parent = AssetCategory::query()->updateOrCreate([
                'slug' => Str::slug($parentName),
            ], [
                'parent_id' => null,
                'name' => $parentName,
                'code' => $this->code($parentName),
                'description' => null,
                'is_active' => true,
            ]);

            foreach ($children as $childName) {
                AssetCategory::query()->updateOrCreate([
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
