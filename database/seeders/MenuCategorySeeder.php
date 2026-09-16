<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Main Dishes', 'code' => 'MAIN_DISHES', 'sort_order' => 10],
            ['name' => 'Beverages', 'code' => 'BEVERAGES', 'sort_order' => 20],
            ['name' => 'Room Service', 'code' => 'ROOM_SERVICE', 'sort_order' => 30],
        ];

        foreach ($categories as $category) {
            MenuCategory::query()->updateOrCreate([
                'code' => $category['code'],
            ], [
                'name' => $category['name'],
                'slug' => Str::slug($category['name']),
                'description' => null,
                'sort_order' => $category['sort_order'],
                'is_active' => true,
            ]);
        }
    }
}
