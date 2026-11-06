<?php

namespace Tests\Feature;

use App\Enums\CashbookTypeEnum;
use App\Enums\UserPortalAccessEnum;
use App\Enums\UserRoleEnum;
use App\Models\Cashbook;
use App\Models\CashbookTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PosCashbookModuleTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('validTransactionStores')]
    public function test_it_stores_pos_transactions_and_updates_balance(string $cashbookType, string $transactionType, string $balance): void
    {
        $cashbook = $this->createCashbook(['type' => $cashbookType, 'opening_balance' => 1000]);
        $user = User::factory()->withPermissions(['cashbook_transactions.create'])->create([
            'portal_access' => UserPortalAccessEnum::Pos->value,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/pos/cashbook-transactions', [
            'cashbook_id' => $cashbook->id,
            'transaction_type' => $transactionType,
            'amount' => 250,
            'transaction_date' => '2026-11-06',
            'remark' => 'POS transaction',
            'created_by' => 999999,
            'balance_after' => 999999,
        ])->assertCreated()
            ->assertJsonPath('data.cashbook_id', $cashbook->id)
            ->assertJsonPath('data.transaction_type', $transactionType)
            ->assertJsonPath('data.amount', '250.00')
            ->assertJsonPath('data.balance_after', $balance)
            ->assertJsonPath('data.created_by', $user->id);

        $this->assertDatabaseHas('cashbook_transactions', [
            'id' => $response->json('data.id'),
            'reference_no' => $response->json('data.reference_no'),
            'cashbook_id' => $cashbook->id,
            'transaction_type' => $transactionType,
            'amount' => 250,
            'balance_after' => $balance,
            'created_by' => $user->id,
            'remark' => 'POS transaction',
        ]);
        $this->assertSame($balance, $cashbook->fresh()->current_balance);
    }

    public static function validTransactionStores(): array
    {
        return [
            'cash income' => ['pos_cash', 'income', '1250.00'],
            'cash expense' => ['pos_cash', 'expense', '750.00'],
            'bank income' => ['pos_bank', 'income', '1250.00'],
            'bank expense' => ['pos_bank', 'expense', '750.00'],
        ];
    }

    #[DataProvider('invalidTransactionStores')]
    public function test_transaction_store_returns_422_without_changing_balance(array $cashbookOverrides, array $payloadOverrides, string $field): void
    {
        $cashbook = $this->createCashbook(array_merge([
            'type' => CashbookTypeEnum::PosCash->value,
            'opening_balance' => 1000,
        ], $cashbookOverrides));

        $this->withHeaders($this->posAuthHeaders())
            ->postJson('/api/v1/pos/cashbook-transactions', array_merge([
                'cashbook_id' => $cashbook->id,
                'transaction_type' => 'income',
                'amount' => 250,
                'transaction_date' => '2026-11-06',
            ], $payloadOverrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('cashbook_transactions', 0);
        $this->assertSame('1000.00', $cashbook->fresh()->current_balance);
    }

    public static function invalidTransactionStores(): array
    {
        return [
            'admin cash' => [['type' => 'cash'], [], 'cashbook_id'],
            'admin bank' => [['type' => 'bank'], [], 'cashbook_id'],
            'inactive cashbook' => [['is_active' => false], [], 'cashbook_id'],
            'insufficient balance' => [[], ['transaction_type' => 'expense', 'amount' => 1001], 'amount'],
            'invalid type' => [[], ['transaction_type' => 'transfer'], 'transaction_type'],
            'zero amount' => [[], ['amount' => 0], 'amount'],
            'invalid date' => [[], ['transaction_date' => 'invalid'], 'transaction_date'],
            'missing cashbook' => [[], ['cashbook_id' => 999999], 'cashbook_id'],
        ];
    }

    public function test_transaction_store_returns_401_without_authentication(): void
    {
        $this->postJson('/api/v1/pos/cashbook-transactions')->assertUnauthorized();
        $this->assertDatabaseCount('cashbook_transactions', 0);
    }

    public function test_transaction_store_returns_403_without_pos_access(): void
    {
        $user = User::factory()->withPermissions(['cashbook_transactions.create'])->create([
            'portal_access' => UserPortalAccessEnum::Admin->value,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/pos/cashbook-transactions')
            ->assertForbidden();

        $this->assertDatabaseCount('cashbook_transactions', 0);
    }

    public function test_transaction_store_returns_403_with_only_view_permission(): void
    {
        $user = User::factory()->withPermissions(['cashbook_transactions.view'])->create([
            'portal_access' => UserPortalAccessEnum::Pos->value,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/pos/cashbook-transactions')
            ->assertForbidden();

        $this->assertDatabaseCount('cashbook_transactions', 0);
    }

    public function test_it_lists_active_cashbooks_for_pos(): void
    {
        $this->createCashbook([
            'code' => 'CASH',
            'name' => 'Main Cash',
            'type' => CashbookTypeEnum::Cash->value,
            'opening_balance' => 1000,
        ]);

        $this->createCashbook([
            'code' => 'BANK',
            'name' => 'Main Bank',
            'type' => CashbookTypeEnum::Bank->value,
            'opening_balance' => 2000,
        ]);

        $posCash = $this->createCashbook([
            'code' => 'POS-CASH',
            'name' => 'POS Cash',
            'type' => CashbookTypeEnum::PosCash->value,
            'opening_balance' => 3000,
        ]);

        $posBank = $this->createCashbook([
            'code' => 'POS-BANK',
            'name' => 'POS Bank',
            'type' => CashbookTypeEnum::PosBank->value,
            'opening_balance' => 4000,
        ]);

        $this->createCashbook([
            'code' => 'INACTIVE',
            'name' => 'Inactive Cash',
            'type' => CashbookTypeEnum::PosCash->value,
            'is_active' => false,
        ]);

        $this->withHeaders($this->posAuthHeaders())
            ->getJson('/api/v1/pos/cashbooks?type=pos_bank&page=1&perPage=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $posBank->id)
            ->assertJsonPath('data.0.type', CashbookTypeEnum::PosBank->value)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 1);

        $this->withHeaders($this->posAuthHeaders())
            ->getJson('/api/v1/pos/cashbooks')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $posBank->id])
            ->assertJsonFragment(['id' => $posCash->id])
            ->assertJsonMissing(['code' => 'CASH'])
            ->assertJsonMissing(['code' => 'BANK'])
            ->assertJsonMissing(['code' => 'INACTIVE']);
    }

    public function test_it_lists_only_pos_cashbook_transactions_including_inactive_history(): void
    {
        $cash = $this->createTransaction($this->createCashbook(['type' => CashbookTypeEnum::PosCash->value]));
        $bank = $this->createTransaction($this->createCashbook(['type' => CashbookTypeEnum::PosBank->value, 'is_active' => false]));
        $this->createTransaction($this->createCashbook(['type' => CashbookTypeEnum::Cash->value]));
        $this->createTransaction($this->createCashbook(['type' => CashbookTypeEnum::Bank->value]));

        $this->withHeaders($this->posAuthHeaders())
            ->getJson('/api/v1/pos/cashbook-transactions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $cash->id)
            ->assertJsonPath('data.1.id', $bank->id)
            ->assertJsonPath('data.0.cashbook.id', $cash->cashbook_id)
            ->assertJsonMissingPath('opening_balance');
    }

    public function test_it_filters_and_paginates_pos_transactions_with_ledger_balances(): void
    {
        $cashbook = $this->createCashbook(['type' => CashbookTypeEnum::PosCash->value, 'opening_balance' => 1000]);
        $this->createTransaction($cashbook, ['transaction_date' => '2026-10-01', 'amount' => 200]);
        $this->createTransaction($cashbook, ['transaction_date' => '2026-10-10', 'transaction_type' => 'expense', 'amount' => 50, 'remark' => 'supplies']);
        $second = $this->createTransaction($cashbook, ['transaction_date' => '2026-10-11', 'transaction_type' => 'expense', 'amount' => 20, 'remark' => 'supplies']);
        $this->createTransaction($cashbook, ['transaction_date' => '2026-10-12', 'amount' => 100]);
        $this->createTransaction($cashbook, ['transaction_date' => '2026-10-13', 'transaction_type' => 'expense', 'amount' => 10, 'remark' => 'transport']);
        $this->createTransaction($cashbook, ['transaction_date' => '2026-10-21', 'transaction_type' => 'expense', 'remark' => 'supplies']);
        $this->createTransaction($this->createCashbook(['type' => CashbookTypeEnum::PosBank->value]), ['transaction_date' => '2026-10-10', 'transaction_type' => 'expense', 'remark' => 'supplies']);

        $this->withHeaders($this->posAuthHeaders())
            ->getJson('/api/v1/pos/cashbook-transactions?'.http_build_query([
                'cashbook_id' => $cashbook->id,
                'search' => 'supplies',
                'transaction_type' => 'expense',
                'date_from' => '2026-10-10',
                'date_to' => '2026-10-20',
                'page' => 2,
                'perPage' => 1,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.amount', '20.00')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('opening_balance', '1200.00')
            ->assertJsonPath('closing_balance', '1220.00');
    }

    public function test_transaction_list_returns_422_for_non_pos_cashbook(): void
    {
        $cashbook = $this->createCashbook();

        $this->withHeaders($this->posAuthHeaders())
            ->getJson('/api/v1/pos/cashbook-transactions?cashbook_id='.$cashbook->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cashbook_id')
            ->assertJsonMissingPath('opening_balance');
    }

    #[DataProvider('invalidTransactionFilters')]
    public function test_transaction_list_returns_422_for_invalid_filters(array $filters, string $field): void
    {
        $this->withHeaders($this->posAuthHeaders())
            ->getJson('/api/v1/pos/cashbook-transactions?'.http_build_query($filters))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public static function invalidTransactionFilters(): array
    {
        return [
            'invalid transaction type' => [['transaction_type' => 'transfer'], 'transaction_type'],
            'invalid start date' => [['date_from' => 'invalid'], 'date_from'],
            'invalid end date' => [['date_to' => 'invalid'], 'date_to'],
            'reversed date range' => [['date_from' => '2026-10-20', 'date_to' => '2026-10-01'], 'date_to'],
            'invalid page' => [['page' => 0], 'page'],
            'oversized page' => [['per_page' => 101], 'per_page'],
            'empty page size' => [['per_page' => 0], 'per_page'],
            'missing cashbook' => [['cashbook_id' => 999999], 'cashbook_id'],
            'invalid cashbook' => [['cashbook_id' => 'invalid'], 'cashbook_id'],
            'oversized search' => [['search' => str_repeat('a', 256)], 'search'],
        ];
    }

    public function test_transaction_list_returns_401_without_authentication(): void
    {
        $this->getJson('/api/v1/pos/cashbook-transactions')->assertUnauthorized();
    }

    public function test_transaction_list_returns_403_without_pos_access(): void
    {
        $user = User::factory()->create(['portal_access' => UserPortalAccessEnum::Admin->value]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/pos/cashbook-transactions')
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTransaction(Cashbook $cashbook, array $overrides = []): CashbookTransaction
    {
        return CashbookTransaction::query()->create(array_merge([
            'reference_no' => fake()->unique()->bothify('CBT-########'),
            'cashbook_id' => $cashbook->id,
            'transaction_type' => 'income',
            'amount' => 100,
            'balance_after' => 100,
            'transaction_date' => '2026-10-10',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCashbook(array $overrides = []): Cashbook
    {
        return Cashbook::query()->create(array_merge([
            'code' => fake()->unique()->bothify('CB-###'),
            'name' => fake()->unique()->words(2, true),
            'type' => CashbookTypeEnum::Cash->value,
            'opening_balance' => 0,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @return array<string, string>
     */
    private function posAuthHeaders(): array
    {
        $user = User::factory()->withPermissions()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => UserRoleEnum::RestaurantAdministrator->value,
            'portal_access' => UserPortalAccessEnum::Pos->value,
        ]);

        $token = $this->postJson('/api/v1/pos/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('data.token');

        return ['Authorization' => "Bearer {$token}"];
    }
}
