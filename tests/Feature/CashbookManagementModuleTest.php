<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookManagementModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_manages_cashbooks_and_posts_ledger_transactions(): void
    {
        $headers = $this->authHeaders();

        $cashbook = $this->withHeaders($headers)->postJson('/api/v1/admin/cashbooks', [
            'code' => ' main-cash ',
            'name' => ' Main Cash ',
            'type' => 'cash',
            'opening_balance' => 100000,
            'is_active' => true,
        ]);

        $cashbook->assertCreated()
            ->assertJsonPath('data.code', 'MAIN-CASH')
            ->assertJsonPath('data.opening_balance', '100000.00')
            ->assertJsonPath('data.current_balance', '100000.00');

        $cashbookId = $cashbook->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/admin/cashbook-transactions', [
            'cashbook_id' => $cashbookId,
            'transaction_type' => 'income',
            'amount' => 25000,
            'transaction_date' => '2026-09-25 09:00:00',
            'remark' => 'Room payment',
        ])->assertCreated()
            ->assertJsonPath('data.transaction_type', 'income')
            ->assertJsonPath('data.balance_after', '125000.00');

        $this->withHeaders($headers)->postJson('/api/v1/admin/cashbook-transactions', [
            'cashbook_id' => $cashbookId,
            'transaction_type' => 'expense',
            'amount' => 5000,
            'transaction_date' => '2026-09-25 10:00:00',
            'remark' => 'Office supplies',
        ])->assertCreated()
            ->assertJsonPath('data.balance_after', '120000.00');

        $this->withHeaders($headers)->getJson("/api/v1/admin/cashbook-transactions?cashbook_id={$cashbookId}&transaction_type=expense&page=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.opening_balance', '100000.00')
            ->assertJsonPath('meta.closing_balance', '120000.00')
            ->assertJsonPath('data.0.remark', 'Office supplies');

        $this->assertDatabaseHas('cashbooks', [
            'id' => $cashbookId,
            'current_balance' => 120000,
        ]);
    }

    public function test_it_rejects_expenses_above_the_current_balance(): void
    {
        $headers = $this->authHeaders();
        $cashbookId = $this->withHeaders($headers)->postJson('/api/v1/admin/cashbooks', [
            'code' => 'KBZ',
            'name' => 'KBZ Bank',
            'type' => 'bank',
            'opening_balance' => 1000,
        ])->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/admin/cashbook-transactions', [
            'cashbook_id' => $cashbookId,
            'transaction_type' => 'expense',
            'amount' => 1001,
            'transaction_date' => '2026-09-25 10:00:00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
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
