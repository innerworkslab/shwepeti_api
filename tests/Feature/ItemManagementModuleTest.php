<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemManagementModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_items_and_nested_unit_conversions_with_one_endpoint(): void
    {
        $headers = $this->authHeaders();
        $setup = $this->inventorySetup($headers);

        $created = $this->withHeaders($headers)->postJson('/api/v1/admin/items', [
            'item_category_id' => $setup['item_category_id'],
            'name' => 'Chicken',
            'code' => 'CHICKEN',
            'sku' => 'SKU-CHICKEN',
            'barcode' => 'BAR-CHICKEN',
            'stock_unit_id' => $setup['kg_id'],
            'description' => 'Raw chicken stock',
            'min_stock' => 10.5,
            'is_active' => true,
            'item_unit_conversions' => [
                [
                    'from_unit_id' => $setup['kg_id'],
                    'to_unit_id' => $setup['g_id'],
                    'conversion_factor' => 1000,
                    'is_active' => true,
                ],
                [
                    'from_unit_id' => $setup['box_id'],
                    'to_unit_id' => $setup['kg_id'],
                    'conversion_factor' => 12,
                    'is_active' => true,
                ],
            ],
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.name', 'Chicken')
            ->assertJsonPath('data.code', 'CHICKEN')
            ->assertJsonPath('data.stock_unit_id', $setup['kg_id'])
            ->assertJsonCount(2, 'data.item_unit_conversions');

        $itemId = $created->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/admin/items', [
            'id' => $itemId,
            'item_category_id' => $setup['item_category_id'],
            'name' => 'Chicken Updated',
            'code' => 'CHICKEN',
            'stock_unit_id' => $setup['kg_id'],
            'min_stock' => 15,
            'is_active' => false,
            'item_unit_conversions' => [
                [
                    'from_unit_id' => $setup['kg_id'],
                    'to_unit_id' => $setup['g_id'],
                    'conversion_factor' => 1000,
                    'is_active' => true,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Chicken Updated')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonCount(1, 'data.item_unit_conversions');

        $this->assertDatabaseMissing('item_unit_conversions', [
            'item_id' => $itemId,
            'from_unit_id' => $setup['box_id'],
            'to_unit_id' => $setup['kg_id'],
        ]);
    }

    public function test_it_saves_and_toggles_item_unit_conversions(): void
    {
        $headers = $this->authHeaders();
        $setup = $this->inventorySetup($headers);
        $itemId = $this->createItem($headers, $setup);

        $created = $this->withHeaders($headers)->postJson('/api/v1/admin/item-unit-conversions', [
            'item_id' => $itemId,
            'from_unit_id' => $setup['kg_id'],
            'to_unit_id' => $setup['g_id'],
            'conversion_factor' => 1000,
            'is_active' => true,
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.item_id', $itemId)
            ->assertJsonPath('data.from_unit_id', $setup['kg_id'])
            ->assertJsonPath('data.to_unit_id', $setup['g_id'])
            ->assertJsonPath('data.is_active', true);

        $conversionId = $created->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/admin/item-unit-conversions', [
            'id' => $conversionId,
            'item_id' => $itemId,
            'from_unit_id' => $setup['kg_id'],
            'to_unit_id' => $setup['g_id'],
            'conversion_factor' => 999,
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.id', $conversionId)
            ->assertJsonPath('data.conversion_factor', '999.000000');

        $this->withHeaders($headers)->postJson("/api/v1/admin/item-unit-conversions/{$conversionId}/toggle-active", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.id', $conversionId)
            ->assertJsonPath('data.is_active', false);
    }

    public function test_it_toggles_item_active_state_and_non_paginated_list_returns_only_active_items(): void
    {
        $headers = $this->authHeaders();
        $setup = $this->inventorySetup($headers);

        $activeItemId = $this->createItem($headers, $setup);
        $inactiveItemId = $this->withHeaders($headers)->postJson('/api/v1/admin/items', [
            'item_category_id' => $setup['item_category_id'],
            'name' => 'Inactive Item',
            'code' => 'INACTIVE_ITEM',
            'stock_unit_id' => $setup['kg_id'],
            'is_active' => false,
        ])->json('data.id');

        $this->withHeaders($headers)->postJson("/api/v1/admin/items/{$activeItemId}/toggle-active", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.id', $activeItemId)
            ->assertJsonPath('data.is_active', false);

        $this->withHeaders($headers)->postJson("/api/v1/admin/items/{$inactiveItemId}/toggle-active", [
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.id', $inactiveItemId)
            ->assertJsonPath('data.is_active', true);

        $this->withHeaders($headers)->getJson('/api/v1/admin/items')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inactiveItemId);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, int>
     */
    private function inventorySetup(array $headers): array
    {
        $weightGroupId = $this->withHeaders($headers)->postJson('/api/v1/admin/unit-groups', [
            'name' => 'Weight',
        ])->json('data.id');

        $quantityGroupId = $this->withHeaders($headers)->postJson('/api/v1/admin/unit-groups', [
            'name' => 'Quantity',
        ])->json('data.id');

        $kgId = $this->withHeaders($headers)->postJson('/api/v1/admin/units', [
            'unit_group_id' => $weightGroupId,
            'name' => 'kg',
            'symbol' => 'kg',
            'is_base' => true,
        ])->json('data.id');

        $gId = $this->withHeaders($headers)->postJson('/api/v1/admin/units', [
            'unit_group_id' => $weightGroupId,
            'name' => 'g',
            'symbol' => 'g',
        ])->json('data.id');

        $boxId = $this->withHeaders($headers)->postJson('/api/v1/admin/units', [
            'unit_group_id' => $quantityGroupId,
            'name' => 'box',
            'symbol' => 'box',
        ])->json('data.id');

        $itemCategoryId = $this->withHeaders($headers)->postJson('/api/v1/admin/item-categories', [
            'name' => 'Raw Materials',
            'code' => 'RAW_MATERIALS',
        ])->json('data.id');

        return [
            'item_category_id' => $itemCategoryId,
            'kg_id' => $kgId,
            'g_id' => $gId,
            'box_id' => $boxId,
        ];
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, int>  $setup
     */
    private function createItem(array $headers, array $setup): int
    {
        return $this->withHeaders($headers)->postJson('/api/v1/admin/items', [
            'item_category_id' => $setup['item_category_id'],
            'name' => 'Rice',
            'code' => 'RICE',
            'stock_unit_id' => $setup['kg_id'],
            'is_active' => true,
        ])->json('data.id');
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
