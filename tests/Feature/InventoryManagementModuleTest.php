<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\InventoryLedger;
use App\Models\InventoryStockBalance;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_unit_groups_with_one_endpoint(): void
    {
        $headers = $this->authHeaders();

        $created = $this->withHeaders($headers)->postJson('/api/v1/admin/unit-groups', [
            'name' => ' Weight ',
            'is_active' => true,
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.name', 'Weight')
            ->assertJsonPath('data.slug', 'weight')
            ->assertJsonPath('data.is_active', true);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $created->json('data.created_at'));

        $unitGroupId = $created->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/admin/unit-groups', [
            'id' => $unitGroupId,
            'name' => 'Weight Updated',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Weight Updated')
            ->assertJsonPath('data.slug', 'weight-updated')
            ->assertJsonPath('data.is_active', false);

        $this->withHeaders($headers)->getJson('/api/v1/admin/unit-groups?is_active=0&page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_it_saves_units_with_one_endpoint(): void
    {
        $headers = $this->authHeaders();

        $unitGroupId = $this->withHeaders($headers)->postJson('/api/v1/admin/unit-groups', [
            'name' => 'Quantity',
        ])->json('data.id');

        $created = $this->withHeaders($headers)->postJson('/api/v1/admin/units', [
            'unit_group_id' => $unitGroupId,
            'name' => 'pcs',
            'symbol' => 'pcs',
            'is_base' => true,
            'is_active' => true,
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.unit_group_id', $unitGroupId)
            ->assertJsonPath('data.name', 'pcs')
            ->assertJsonPath('data.is_base', true);

        $unitId = $created->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/admin/units', [
            'id' => $unitId,
            'unit_group_id' => $unitGroupId,
            'name' => 'piece',
            'symbol' => 'pc',
            'is_base' => false,
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'piece')
            ->assertJsonPath('data.symbol', 'pc')
            ->assertJsonPath('data.is_base', false);

        $this->withHeaders($headers)->getJson("/api/v1/admin/units?unit_group_id={$unitGroupId}&page=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_it_saves_item_categories_with_one_endpoint(): void
    {
        $headers = $this->authHeaders();

        $parent = $this->withHeaders($headers)->postJson('/api/v1/admin/item-categories', [
            'name' => 'Raw Materials',
            'code' => 'RAW MATERIALS',
            'is_active' => true,
        ]);

        $parent->assertCreated()
            ->assertJsonPath('data.name', 'Raw Materials')
            ->assertJsonPath('data.slug', 'raw-materials')
            ->assertJsonPath('data.code', 'RAW_MATERIALS');

        $parentId = $parent->json('data.id');

        $child = $this->withHeaders($headers)->postJson('/api/v1/admin/item-categories', [
            'parent_id' => $parentId,
            'name' => 'Rice & Grain',
            'code' => 'RICE_GRAIN',
            'description' => 'Rice and grain stock',
            'is_active' => true,
        ]);

        $child->assertCreated()
            ->assertJsonPath('data.parent_id', $parentId)
            ->assertJsonPath('data.name', 'Rice & Grain')
            ->assertJsonPath('data.slug', 'rice-grain');

        $childId = $child->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/admin/item-categories', [
            'id' => $childId,
            'parent_id' => $parentId,
            'name' => 'Rice and Grain',
            'code' => 'RICE_GRAIN',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Rice and Grain')
            ->assertJsonPath('data.slug', 'rice-and-grain')
            ->assertJsonPath('data.is_active', false);

        $this->withHeaders($headers)->getJson("/api/v1/admin/item-categories?parent_id={$parentId}&page=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_it_toggles_unit_group_active_state(): void
    {
        $headers = $this->authHeaders();

        $unitGroupId = $this->withHeaders($headers)->postJson('/api/v1/admin/unit-groups', [
            'name' => 'Volume',
            'is_active' => true,
        ])->json('data.id');

        $this->withHeaders($headers)->postJson("/api/v1/admin/unit-groups/{$unitGroupId}/toggle-active", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.id', $unitGroupId)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('unit_groups', [
            'id' => $unitGroupId,
            'is_active' => false,
        ]);
    }

    public function test_it_toggles_unit_active_state(): void
    {
        $headers = $this->authHeaders();

        $unitGroupId = $this->withHeaders($headers)->postJson('/api/v1/admin/unit-groups', [
            'name' => 'Length',
        ])->json('data.id');

        $unitId = $this->withHeaders($headers)->postJson('/api/v1/admin/units', [
            'unit_group_id' => $unitGroupId,
            'name' => 'm',
            'symbol' => 'm',
            'is_active' => true,
        ])->json('data.id');

        $this->withHeaders($headers)->postJson("/api/v1/admin/units/{$unitId}/toggle-active", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.id', $unitId)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('units', [
            'id' => $unitId,
            'is_active' => false,
        ]);
    }

    public function test_it_toggles_item_category_active_state(): void
    {
        $headers = $this->authHeaders();

        $itemCategoryId = $this->withHeaders($headers)->postJson('/api/v1/admin/item-categories', [
            'name' => 'Beverages',
            'code' => 'BEVERAGES',
            'is_active' => true,
        ])->json('data.id');

        $this->withHeaders($headers)->postJson("/api/v1/admin/item-categories/{$itemCategoryId}/toggle-active", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.id', $itemCategoryId)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('item_categories', [
            'id' => $itemCategoryId,
            'is_active' => false,
        ]);
    }

    public function test_non_paginated_inventory_lists_return_only_active_records(): void
    {
        $headers = $this->authHeaders();

        $activeUnitGroupId = $this->withHeaders($headers)->postJson('/api/v1/admin/unit-groups', [
            'name' => 'Active Group',
            'is_active' => true,
        ])->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/admin/unit-groups', [
            'name' => 'Inactive Group',
            'is_active' => false,
        ]);

        $activeUnitId = $this->withHeaders($headers)->postJson('/api/v1/admin/units', [
            'unit_group_id' => $activeUnitGroupId,
            'name' => 'active unit',
            'symbol' => 'au',
            'is_active' => true,
        ])->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/admin/units', [
            'unit_group_id' => $activeUnitGroupId,
            'name' => 'inactive unit',
            'symbol' => 'iu',
            'is_active' => false,
        ]);

        $activeCategoryId = $this->withHeaders($headers)->postJson('/api/v1/admin/item-categories', [
            'name' => 'Active Category',
            'code' => 'ACTIVE_CATEGORY',
            'is_active' => true,
        ])->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/admin/item-categories', [
            'name' => 'Inactive Category',
            'code' => 'INACTIVE_CATEGORY',
            'is_active' => false,
        ]);

        $this->withHeaders($headers)->getJson('/api/v1/admin/unit-groups')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeUnitGroupId);

        $this->withHeaders($headers)->getJson('/api/v1/admin/units')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeUnitId);

        $this->withHeaders($headers)->getJson('/api/v1/admin/item-categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeCategoryId);
    }

    public function test_it_includes_inventory_stock_balance_movement_totals(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 2)->startOfDay());

        $headers = $this->authHeaders();
        [$item, $warehouse, $unit] = $this->inventorySetup();

        InventoryStockBalance::query()->create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 14,
            'reserved_quantity' => 0,
            'available_quantity' => 14,
        ]);

        $this->createInventoryLedger($item, $warehouse, $unit, 10, 10, '2026-09-30 09:00:00');
        $this->createInventoryLedger($item, $warehouse, $unit, 5, 15, '2026-10-01 09:00:00');
        $this->createInventoryLedger($item, $warehouse, $unit, -3, 12, '2026-10-02 09:00:00');
        $this->createInventoryLedger($item, $warehouse, $unit, 2, 14, '2026-11-01 09:00:00');

        $this->withHeaders($headers)
            ->getJson("/api/v1/admin/inventory-stock-balances?item_id={$item->id}&warehouse_id={$warehouse->id}&date_from=2026-10-01&date_to=2026-10-31&page=1")
            ->assertOk()
            ->assertJsonPath('data.0.opening_balance', '10.000000')
            ->assertJsonPath('data.0.in_balance', '5.000000')
            ->assertJsonPath('data.0.out_balance', '3.000000')
            ->assertJsonPath('data.0.closing_balance', '12.000000');

        $this->withHeaders($headers)
            ->getJson("/api/v1/admin/inventory-stock-balances?item_id={$item->id}&warehouse_id={$warehouse->id}&page=1")
            ->assertOk()
            ->assertJsonPath('data.0.opening_balance', '15.000000')
            ->assertJsonPath('data.0.in_balance', '0.000000')
            ->assertJsonPath('data.0.out_balance', '3.000000')
            ->assertJsonPath('data.0.closing_balance', '12.000000');

        $this->withHeaders($headers)
            ->getJson("/api/v1/admin/inventory-stock-balances?item_id={$item->id}&date_from=2026-10-01")
            ->assertOk()
            ->assertJsonPath('data.0.opening_balance', '10.000000')
            ->assertJsonPath('data.0.in_balance', '5.000000')
            ->assertJsonPath('data.0.out_balance', '3.000000')
            ->assertJsonPath('data.0.closing_balance', '12.000000');

        $this->withHeaders($headers)
            ->getJson("/api/v1/admin/inventory-stock-balances?item_id={$item->id}&date_to=2026-11-01")
            ->assertOk()
            ->assertJsonPath('data.0.opening_balance', '15.000000')
            ->assertJsonPath('data.0.in_balance', '2.000000')
            ->assertJsonPath('data.0.out_balance', '3.000000')
            ->assertJsonPath('data.0.closing_balance', '14.000000');
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

    /**
     * @return array{Item, Warehouse, Unit}
     */
    private function inventorySetup(): array
    {
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

        return [$item, $warehouse, $unit];
    }

    private function createInventoryLedger(Item $item, Warehouse $warehouse, Unit $unit, float $baseQuantity, float $balanceQuantity, string $transactionDate): void
    {
        InventoryLedger::query()->create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'transaction_type' => $baseQuantity > 0 ? 'in' : 'out',
            'quantity' => abs($baseQuantity),
            'unit_id' => $unit->id,
            'base_quantity' => $baseQuantity,
            'balance_quantity' => $balanceQuantity,
            'transaction_date' => $transactionDate,
        ]);
    }
}
