<?php

namespace Tests\Feature;

use App\Models\Cashbook;
use Database\Seeders\CashbookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_cashbooks_without_resetting_existing_balances(): void
    {
        $this->seed(CashbookSeeder::class);

        $cashbook = Cashbook::query()->where('code', 'MAIN-CASH')->firstOrFail();
        $cashbook->forceFill(['current_balance' => 250000])->save();

        $this->seed(CashbookSeeder::class);

        $this->assertDatabaseCount('cashbooks', 3);
        $this->assertDatabaseHas('cashbooks', [
            'code' => 'MAIN-CASH',
            'type' => 'cash',
            'opening_balance' => 0,
            'current_balance' => 250000,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('cashbooks', [
            'code' => 'KBZ-BANK',
            'type' => 'bank',
        ]);
    }
}
