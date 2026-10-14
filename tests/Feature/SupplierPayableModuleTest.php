<?php

namespace Tests\Feature;

use App\Enums\CashbookTypeEnum;
use App\Enums\SupplierApPaymentStatusEnum;
use App\Enums\SupplierTypeEnum;
use App\Enums\UserRoleEnum;
use App\Models\Cashbook;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Supplier;
use App\Models\SupplierApPayment;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPayableModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_in_requires_total_amount_and_limits_paid_amount(): void
    {
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson('/api/v1/admin/stock-ins', [
            'total_amount' => 100,
            'paid_amount' => 101,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('paid_amount');

        $this->withHeaders($headers)->postJson('/api/v1/admin/stock-ins', [
            'paid_amount' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('total_amount');
    }

    public function test_partially_paid_stock_in_and_ap_payment_update_cash_and_supplier_balances(): void
    {
        $headers = $this->authHeaders();
        $warehouse = Warehouse::query()->create(['code' => 'MAIN', 'name' => 'Main Warehouse']);
        $unitGroup = UnitGroup::query()->create(['name' => 'Quantity', 'slug' => 'quantity']);
        $unit = Unit::query()->create(['unit_group_id' => $unitGroup->id, 'name' => 'Piece', 'symbol' => 'pc', 'is_base' => true]);
        $category = ItemCategory::query()->create(['name' => 'Supplies', 'slug' => 'supplies', 'code' => 'SUPPLIES']);
        $item = Item::query()->create([
            'item_category_id' => $category->id,
            'name' => 'Towel',
            'code' => 'TOWEL',
            'stock_unit_id' => $unit->id,
        ]);

        $supplier = $this->withHeaders($headers)->postJson('/api/v1/admin/suppliers', [
            'supplier_code' => 'SUP-001',
            'name' => 'Hotel Supply Co.',
            'credit_limit_amount' => 2000,
            'supplier_type' => 'wholesale',
        ])->assertCreated()
            ->assertJsonPath('data.payable_balance', '0.00');

        $supplierId = $supplier->json('data.id');
        $cashbookId = $this->withHeaders($headers)->postJson('/api/v1/admin/cashbooks', [
            'code' => 'MAIN-CASH',
            'name' => 'Main Cash',
            'type' => 'cash',
            'opening_balance' => 5000,
        ])->json('data.id');

        $stockIn = $this->withHeaders($headers)->postJson('/api/v1/admin/stock-ins', [
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplierId,
            'cashbook_id' => $cashbookId,
            'total_amount' => 1000,
            'paid_amount' => 400,
            'transaction_date' => '2026-09-25 09:00:00',
            'items' => [[
                'item_id' => $item->id,
                'unit_id' => $unit->id,
                'quantity' => 10,
                'unit_cost' => 100,
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.total_amount', '1000.00')
            ->assertJsonPath('data.paid_amount', '400.00')
            ->assertJsonPath('data.payable_amount', '600.00');

        $stockInId = $stockIn->json('data.id');

        $this->withHeaders($headers)->postJson("/api/v1/admin/stock-ins/{$stockInId}/status", [
            'status' => 'posted',
        ])->assertOk();

        $this->assertDatabaseHas('suppliers', ['id' => $supplierId, 'payable_balance' => 600]);
        $this->assertDatabaseHas('cashbooks', ['id' => $cashbookId, 'current_balance' => 4600]);
        $this->assertDatabaseHas('supplier_ap_ledgers', ['supplier_id' => $supplierId, 'transaction_type' => 'purchase', 'amount' => 600]);

        $payment = $this->withHeaders($headers)->postJson('/api/v1/admin/supplier-ap-payments', [
            'supplier_id' => $supplierId,
            'cashbook_id' => $cashbookId,
            'amount' => 250,
            'payment_date' => '2026-09-26 09:00:00',
            'remark' => 'Partial settlement',
        ])->assertCreated();

        $paymentId = $payment->json('data.id');

        $this->withHeaders($headers)->postJson("/api/v1/admin/supplier-ap-payments/{$paymentId}/status", [
            'status' => 'posted',
        ])->assertOk()
            ->assertJsonPath('data.status', 'posted');

        $this->assertDatabaseHas('suppliers', ['id' => $supplierId, 'payable_balance' => 350]);
        $this->assertDatabaseHas('cashbooks', ['id' => $cashbookId, 'current_balance' => 4350]);
        $this->assertDatabaseHas('supplier_ap_ledgers', ['supplier_id' => $supplierId, 'transaction_type' => 'payment', 'amount' => 250]);
        $this->assertDatabaseCount('cashbook_transactions', 2);
    }

    public function test_it_lists_supplier_ap_payments_by_supplier(): void
    {
        $headers = $this->authHeaders();
        $supplier = $this->createSupplier('SUP-001');
        $otherSupplier = $this->createSupplier('SUP-002');
        $cashbook = $this->createCashbook();

        SupplierApPayment::query()->create([
            'reference_no' => 'SAP-A-001',
            'supplier_id' => $supplier->id,
            'cashbook_id' => $cashbook->id,
            'amount' => 100,
            'payment_date' => '2026-09-25 09:00:00',
            'status' => SupplierApPaymentStatusEnum::Draft->value,
        ]);

        $postedPayment = SupplierApPayment::query()->create([
            'reference_no' => 'SAP-A-002',
            'supplier_id' => $supplier->id,
            'cashbook_id' => $cashbook->id,
            'amount' => 200,
            'payment_date' => '2026-09-26 09:00:00',
            'status' => SupplierApPaymentStatusEnum::Posted->value,
        ]);

        SupplierApPayment::query()->create([
            'reference_no' => 'SAP-B-001',
            'supplier_id' => $otherSupplier->id,
            'cashbook_id' => $cashbook->id,
            'amount' => 300,
            'payment_date' => '2026-09-27 09:00:00',
            'status' => SupplierApPaymentStatusEnum::Posted->value,
        ]);

        $this->withHeaders($headers)
            ->getJson("/api/v1/admin/suppliers/{$supplier->id}/ap-payments?status=posted&page=1&perPage=10")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $postedPayment->id)
            ->assertJsonPath('data.0.supplier_id', $supplier->id)
            ->assertJsonPath('data.0.status', SupplierApPaymentStatusEnum::Posted->value)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissing(['reference_no' => 'SAP-B-001']);
    }

    private function createSupplier(string $supplierCode): Supplier
    {
        return Supplier::query()->create([
            'supplier_code' => $supplierCode,
            'name' => fake()->unique()->company(),
            'supplier_type' => SupplierTypeEnum::WHOLESALE->value,
            'is_active' => true,
        ]);
    }

    private function createCashbook(): Cashbook
    {
        return Cashbook::query()->create([
            'code' => fake()->unique()->bothify('CB-###'),
            'name' => fake()->unique()->words(2, true),
            'type' => CashbookTypeEnum::Cash->value,
            'opening_balance' => 1000,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        $admin = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => UserRoleEnum::HotelAdministrator->value,
        ]);

        $token = $this->postJson('/api/v1/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->json('data.token');

        return ['Authorization' => "Bearer {$token}"];
    }
}
