<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            ['code' => 'MAIN', 'name' => 'Main Warehouse'],
            ['code' => 'KITCHEN', 'name' => 'Kitchen Store'],
            ['code' => 'BAR', 'name' => 'Bar Store'],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::query()->updateOrCreate([
                'code' => $warehouse['code'],
            ], [
                'name' => $warehouse['name'],
                'description' => null,
                'is_active' => true,
            ]);
        }
    }
}
