<?php

namespace Database\Seeders;

use App\Models\RoomCategory;
use Illuminate\Database\Seeder;

class RoomCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Standard', 'description' => 'Comfortable room for short stays.'],
            ['name' => 'Deluxe', 'description' => 'Larger room with upgraded amenities.'],
            ['name' => 'Suite', 'description' => 'Premium room for family or long stays.'],
        ];

        foreach ($categories as $category) {
            RoomCategory::query()->updateOrCreate([
                'name' => $category['name'],
            ], [
                'description' => $category['description'],
                'is_active' => true,
            ]);
        }
    }
}
