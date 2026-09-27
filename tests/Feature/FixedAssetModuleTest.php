<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedAssetModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_manages_asset_categories(): void
    {
        $headers = $this->authHeaders();

        $parent = $this->withHeaders($headers)->postJson('/api/v1/admin/asset-categories', [
            'name' => 'Equipment',
            'code' => ' equipment ',
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'equipment')
            ->assertJsonPath('data.code', 'EQUIPMENT');

        $parentId = $parent->json('data.id');

        $child = $this->withHeaders($headers)->postJson('/api/v1/admin/asset-categories', [
            'parent_id' => $parentId,
            'name' => 'Kitchen Equipment',
            'code' => 'KITCHEN-EQUIPMENT',
        ])->assertCreated()
            ->assertJsonPath('data.parent_id', $parentId)
            ->assertJsonPath('data.code', 'KITCHEN_EQUIPMENT');

        $childId = $child->json('data.id');

        $this->withHeaders($headers)->postJson("/api/v1/admin/asset-categories/{$childId}/toggle-active", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->withHeaders($headers)->getJson('/api/v1/admin/asset-categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $parentId);

        $this->withHeaders($headers)->deleteJson("/api/v1/admin/asset-categories/{$parentId}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('asset_category');
    }

    public function test_posting_a_fixed_asset_creates_exactly_one_cashbook_expense(): void
    {
        $headers = $this->authHeaders();
        $categoryId = $this->createCategory($headers);
        $cashbookId = $this->createCashbook($headers, 10000);

        $created = $this->withHeaders($headers)->postJson('/api/v1/admin/fixed-assets', [
            'asset_code' => ' FA 001 ',
            'asset_category_id' => $categoryId,
            'cashbook_id' => $cashbookId,
            'name' => 'Commercial Refrigerator',
            'serial_number' => 'SN-10001',
            'location' => 'Main Kitchen',
            'purchase_date' => '2026-09-27 10:00:00',
            'purchase_amount' => 2500,
            'warranty_expiry_date' => '2027-09-27',
            'documents' => [
                ['name' => 'Invoice', 'path' => 'fixed-assets/fa-001/invoice.pdf'],
                ['name' => 'Warranty', 'path' => 'fixed-assets/fa-001/warranty.pdf'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.asset_code', 'FA-001')
            ->assertJsonPath('data.documents.0.name', 'Invoice')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.purchase_amount', '2500.00');

        $fixedAssetId = $created->json('data.id');

        $this->assertDatabaseCount('cashbook_transactions', 0);

        $this->withHeaders($headers)->postJson("/api/v1/admin/fixed-assets/{$fixedAssetId}/status", [
            'status' => 'posted',
        ])->assertOk()
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonPath('data.cashbook_transaction.transaction_type', 'expense')
            ->assertJsonPath('data.cashbook_transaction.amount', '2500.00')
            ->assertJsonPath('data.cashbook_transaction.reference_id', $fixedAssetId);

        $this->assertDatabaseCount('cashbook_transactions', 1);
        $this->assertDatabaseHas('cashbook_transactions', [
            'cashbook_id' => $cashbookId,
            'transaction_type' => 'expense',
            'amount' => 2500,
            'reference_type' => 'fixed_asset',
            'reference_id' => $fixedAssetId,
        ]);
        $this->assertDatabaseHas('cashbooks', [
            'id' => $cashbookId,
            'current_balance' => 7500,
        ]);

        $this->withHeaders($headers)->postJson("/api/v1/admin/fixed-assets/{$fixedAssetId}/status", [
            'status' => 'posted',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseCount('cashbook_transactions', 1);

        $this->withHeaders($headers)->postJson('/api/v1/admin/fixed-assets', [
            'id' => $fixedAssetId,
            'asset_code' => 'FA-001',
            'asset_category_id' => $categoryId,
            'cashbook_id' => $cashbookId,
            'name' => 'Changed Name',
            'purchase_date' => '2026-09-27 10:00:00',
            'purchase_amount' => 2500,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('id');

        $this->withHeaders($headers)->deleteJson("/api/v1/admin/fixed-assets/{$fixedAssetId}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id');
    }

    public function test_failed_fixed_asset_post_does_not_change_status_or_create_a_transaction(): void
    {
        $headers = $this->authHeaders();
        $categoryId = $this->createCategory($headers);
        $cashbookId = $this->createCashbook($headers, 1000);

        $fixedAssetId = $this->withHeaders($headers)->postJson('/api/v1/admin/fixed-assets', [
            'asset_code' => 'FA-002',
            'asset_category_id' => $categoryId,
            'cashbook_id' => $cashbookId,
            'name' => 'Generator',
            'purchase_date' => '2026-09-27 11:00:00',
            'purchase_amount' => 1001,
        ])->json('data.id');

        $this->withHeaders($headers)->postJson("/api/v1/admin/fixed-assets/{$fixedAssetId}/status", [
            'status' => 'posted',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertDatabaseHas('fixed_assets', ['id' => $fixedAssetId, 'status' => 'draft']);
        $this->assertDatabaseHas('cashbooks', ['id' => $cashbookId, 'current_balance' => 1000]);
        $this->assertDatabaseCount('cashbook_transactions', 0);
    }

    private function createCategory(array $headers): int
    {
        return $this->withHeaders($headers)->postJson('/api/v1/admin/asset-categories', [
            'name' => 'Equipment',
            'code' => 'EQUIPMENT',
        ])->json('data.id');
    }

    private function createCashbook(array $headers, float $openingBalance): int
    {
        return $this->withHeaders($headers)->postJson('/api/v1/admin/cashbooks', [
            'code' => 'MAIN-CASH',
            'name' => 'Main Cash',
            'type' => 'cash',
            'opening_balance' => $openingBalance,
        ])->json('data.id');
    }

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
