<?php

namespace Database\Factories;

use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FixedAssetDepreciation> */
class FixedAssetDepreciationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fixed_asset_id' => FixedAsset::factory()->state(['status' => 'posted', 'depreciation_enabled' => true, 'depreciation_start_date' => '2026-01-01', 'useful_life_months' => 10, 'residual_value' => 0, 'accumulated_depreciation' => 100]),
            'period' => '2026-01-01',
            'amount' => '100.00',
            'opening_book_value' => '1000.00',
            'closing_book_value' => '900.00',
        ];
    }
}
