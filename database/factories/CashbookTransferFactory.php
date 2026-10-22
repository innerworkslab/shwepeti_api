<?php

namespace Database\Factories;

use App\Models\Cashbook;
use App\Models\CashbookTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CashbookTransfer> */
class CashbookTransferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference_no' => 'CBF-'.fake()->uuid(),
            'request_id' => fake()->uuid(),
            'from_cashbook_id' => fn (): int => Cashbook::query()->create([
                'code' => fake()->unique()->bothify('CASH-########'), 'name' => 'Source', 'type' => 'pos_cash', 'opening_balance' => '1000.00', 'is_active' => true,
            ])->id,
            'to_cashbook_id' => fn (): int => Cashbook::query()->create([
                'code' => fake()->unique()->bothify('BANK-########'), 'name' => 'Destination', 'type' => 'pos_bank', 'opening_balance' => '0.00', 'is_active' => true,
            ])->id,
            'amount' => '25.50',
            'transfer_date' => '2026-10-22 10:00:00',
            'remark' => 'Cashbook transfer',
        ];
    }
}
