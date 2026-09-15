<?php

namespace Database\Seeders;

use App\Models\UnitGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $unitGroups = [
            'Weight' => [
                ['name' => 'kg', 'symbol' => 'kg', 'is_base' => true],
                ['name' => 'g', 'symbol' => 'g', 'is_base' => false],
            ],
            'Volume' => [
                ['name' => 'L', 'symbol' => 'L', 'is_base' => true],
                ['name' => 'ml', 'symbol' => 'ml', 'is_base' => false],
            ],
            'Quantity' => [
                ['name' => 'pcs', 'symbol' => 'pcs', 'is_base' => true],
                ['name' => 'bottle', 'symbol' => 'bottle', 'is_base' => false],
                ['name' => 'box', 'symbol' => 'box', 'is_base' => false],
                ['name' => 'pack', 'symbol' => 'pack', 'is_base' => false],
                ['name' => 'carton', 'symbol' => 'carton', 'is_base' => false],
            ],
            'Length' => [
                ['name' => 'm', 'symbol' => 'm', 'is_base' => true],
                ['name' => 'cm', 'symbol' => 'cm', 'is_base' => false],
            ],
        ];

        foreach ($unitGroups as $groupName => $units) {
            $unitGroup = UnitGroup::query()->updateOrCreate([
                'slug' => Str::slug($groupName),
            ], [
                'name' => $groupName,
                'is_active' => true,
            ]);

            foreach ($units as $unit) {
                $unitGroup->units()->updateOrCreate([
                    'symbol' => $unit['symbol'],
                ], [
                    'name' => $unit['name'],
                    'is_base' => $unit['is_base'],
                    'is_active' => true,
                ]);
            }
        }
    }
}
