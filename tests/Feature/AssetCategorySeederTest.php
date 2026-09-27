<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use Database\Seeders\AssetCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetCategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_asset_category_hierarchy_idempotently(): void
    {
        $this->seed(AssetCategorySeeder::class);
        $this->seed(AssetCategorySeeder::class);

        $this->assertDatabaseCount('asset_categories', 18);
        $this->assertDatabaseHas('asset_categories', [
            'name' => 'Equipment',
            'code' => 'EQUIPMENT',
            'parent_id' => null,
            'is_active' => true,
        ]);

        $equipment = AssetCategory::query()->where('code', 'EQUIPMENT')->firstOrFail();

        $this->assertDatabaseHas('asset_categories', [
            'name' => 'Kitchen Equipment',
            'code' => 'KITCHEN_EQUIPMENT',
            'parent_id' => $equipment->id,
            'is_active' => true,
        ]);
    }
}
