<?php

namespace Database\Seeders;

use App\Enums\CashbookTypeEnum;
use App\Models\Cashbook;
use Illuminate\Database\Seeder;

class CashbookSeeder extends Seeder
{
    public function run(): void
    {
        $cashbooks = [
            [
                'code' => 'MAIN-CASH',
                'name' => 'Main Cash',
                'type' => CashbookTypeEnum::Cash->value,
                'description' => 'Main operating cashbook.',
            ],
            [
                'code' => 'KBZ-BANK',
                'name' => 'KBZ Bank',
                'type' => CashbookTypeEnum::Bank->value,
                'description' => 'KBZ bank account.',
            ],
            [
                'code' => 'AYA-BANK',
                'name' => 'AYA Bank',
                'type' => CashbookTypeEnum::Bank->value,
                'description' => 'AYA bank account.',
            ],
        ];

        foreach ($cashbooks as $cashbook) {
            Cashbook::query()->firstOrCreate([
                'code' => $cashbook['code'],
            ], [
                ...$cashbook,
                'opening_balance' => 0,
                'is_active' => true,
            ]);
        }
    }
}
