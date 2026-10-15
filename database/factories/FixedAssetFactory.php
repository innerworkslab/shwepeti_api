<?php

namespace Database\Factories;

use App\Models\AssetCategory;
use App\Models\Cashbook;
use App\Models\FixedAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FixedAsset> */
class FixedAssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference_no' => fake()->unique()->bothify('FAS-########'),
            'asset_code' => fake()->unique()->bothify('FA-########'),
            'asset_category_id' => fn (): int => AssetCategory::query()->create([
                'name' => 'Equipment', 'slug' => fake()->unique()->slug(), 'code' => fake()->unique()->bothify('CAT-########'),
            ])->id,
            'cashbook_id' => fn (): int => Cashbook::query()->create([
                'name' => 'Main Cash', 'code' => fake()->unique()->bothify('CASH-########'), 'type' => 'cash', 'opening_balance' => 10000,
            ])->id,
            'name' => 'Equipment',
            'purchase_date' => '2026-01-01',
            'purchase_amount' => '1000.00',
            'status' => 'draft',
        ];
    }
}
