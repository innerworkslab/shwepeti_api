<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            RoomCategorySeeder::class,
            RoomSeeder::class,
            UnitSeeder::class,
            ItemCategorySeeder::class,
            AssetCategorySeeder::class,
            ItemSeeder::class,
            WarehouseSeeder::class,
            MenuCategorySeeder::class,
            MenuSeeder::class,
            CashbookSeeder::class,
            SupplierSeeder::class,
        ]);
    }
}
