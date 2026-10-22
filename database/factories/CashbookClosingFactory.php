<?php

namespace Database\Factories;

use App\Models\Cashbook;
use App\Models\CashbookClosing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashbookClosing>
 */
class CashbookClosingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cashbook_id' => fn (): int => Cashbook::query()->create([
                'code' => fake()->unique()->bothify('CB-########'),
                'name' => 'Main Cash',
                'type' => 'cash',
                'opening_balance' => 1000,
                'is_active' => true,
            ])->id,
            'closing_date' => today()->toDateString(),
            'closed_at' => now(),
            'opening_balance' => '1000.00',
            'closing_balance' => '1000.00',
            'created_by' => User::factory(),
            'remark' => null,
        ];
    }
}
