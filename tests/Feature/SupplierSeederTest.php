<?php

namespace Tests\Feature;

use App\Enums\SupplierTypeEnum;
use App\Models\Supplier;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_suppliers_and_supports_factory_states(): void
    {
        $this->seed(SupplierSeeder::class);
        $this->seed(SupplierSeeder::class);

        $this->assertDatabaseCount('suppliers', 3);
        $this->assertDatabaseHas('suppliers', [
            'supplier_code' => 'SUP-001',
            'supplier_type' => SupplierTypeEnum::WHOLESALE->value,
            'is_active' => true,
        ]);

        $supplier = Supplier::factory()->retail()->inactive()->create();

        $this->assertSame(SupplierTypeEnum::RETAIL, $supplier->supplier_type);
        $this->assertFalse($supplier->is_active);
    }
}
