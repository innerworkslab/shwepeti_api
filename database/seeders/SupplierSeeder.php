<?php

namespace Database\Seeders;

use App\Enums\SupplierTypeEnum;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'supplier_code' => 'SUP-001',
                'name' => 'Shwe Food Supply',
                'phone_number' => '09111111111',
                'email' => 'sales@shwefoodsupply.com',
                'address' => 'Yangon',
                'bank_account_no' => null,
                'credit_limit_amount' => 5000000,
                'supplier_type' => SupplierTypeEnum::WHOLESALE->value,
            ],
            [
                'supplier_code' => 'SUP-002',
                'name' => 'Golden Beverage Distribution',
                'phone_number' => '09222222222',
                'email' => 'orders@goldenbeverage.com',
                'address' => 'Yangon',
                'bank_account_no' => null,
                'credit_limit_amount' => 3000000,
                'supplier_type' => SupplierTypeEnum::WHOLESALE->value,
            ],
            [
                'supplier_code' => 'SUP-003',
                'name' => 'Local Market Supplier',
                'phone_number' => '09333333333',
                'email' => null,
                'address' => 'Yangon',
                'bank_account_no' => null,
                'credit_limit_amount' => 500000,
                'supplier_type' => SupplierTypeEnum::RETAIL->value,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->updateOrCreate([
                'supplier_code' => $supplier['supplier_code'],
            ], [
                ...$supplier,
                'is_active' => true,
            ]);
        }
    }
}
