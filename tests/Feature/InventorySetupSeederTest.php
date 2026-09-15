<?php

namespace Tests\Feature;

use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventorySetupSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_unit_groups_and_units(): void
    {
        $this->seed(UnitSeeder::class);

        $this->assertDatabaseCount('unit_groups', 4);
        $this->assertDatabaseCount('units', 11);

        $this->assertDatabaseHas('unit_groups', [
            'name' => 'Weight',
            'slug' => 'weight',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('units', [
            'name' => 'kg',
            'symbol' => 'kg',
            'is_base' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('units', [
            'name' => 'carton',
            'symbol' => 'carton',
            'is_base' => false,
            'is_active' => true,
        ]);
    }

    public function test_it_seeds_parent_and_child_item_categories(): void
    {
        $this->seed(ItemCategorySeeder::class);

        $this->assertDatabaseCount('item_categories', 21);

        $this->assertDatabaseHas('item_categories', [
            'parent_id' => null,
            'name' => 'Raw Materials',
            'slug' => 'raw-materials',
            'code' => 'RAW_MATERIALS',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('item_categories', [
            'name' => 'Rice & Grain',
            'slug' => 'rice-grain',
            'code' => 'RICE_AND_GRAIN',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('item_categories', [
            'name' => 'Equipment Accessories',
            'slug' => 'equipment-accessories',
            'code' => 'EQUIPMENT_ACCESSORIES',
            'is_active' => true,
        ]);
    }
}
