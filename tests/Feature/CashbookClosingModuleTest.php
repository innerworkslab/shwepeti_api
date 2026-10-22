<?php

namespace Tests\Feature;

use App\Enums\UserPortalAccessEnum;
use App\Enums\UserRoleEnum;
use App\Models\Cashbook;
use App\Models\CashbookClosing;
use App\Models\User;
use App\Services\CashbookTransactions\CashbookTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CashbookClosingModuleTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('portals')]
    public function test_it_records_daily_balances_and_carries_the_previous_close_forward(string $portal): void
    {
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(21, 30));
        $actor = $this->authenticate($portal);
        $cashbook = $this->cashbook();
        $this->postTransaction($cashbook, 'income', 250);
        $this->postTransaction($cashbook, 'expense', 50);

        $this->postJson("/api/v1/{$portal}/cashbooks/close", [
            'cashbook_id' => $cashbook->id,
            'remark' => 'End of day',
            'closing_balance' => 999999,
            'closed_at' => '2020-01-01 00:00:00',
            'created_by' => 999999,
        ])->assertCreated()
            ->assertJsonPath('data.closing_date', '2026-10-22')
            ->assertJsonPath('data.closed_at', '2026-10-22 21:30:00')
            ->assertJsonPath('data.opening_balance', '1000.00')
            ->assertJsonPath('data.closing_balance', '1200.00')
            ->assertJsonPath('data.created_by', $actor->id)
            ->assertJsonPath('data.remark', 'End of day');

        $this->assertDatabaseHas('cashbook_closings', [
            'cashbook_id' => $cashbook->id,
            'closed_at' => '2026-10-22 21:30:00',
            'closing_balance' => 1200,
            'created_by' => $actor->id,
        ]);
        $this->assertDatabaseHas('cashbooks', ['id' => $cashbook->id, 'current_balance' => 1200, 'opening_balance' => 1000, 'is_active' => true]);
        $this->assertDatabaseCount('cashbook_transactions', 2);

        $this->postTransaction($cashbook, 'income', 100);
        $this->travelTo(now()->addDay());
        $this->postJson("/api/v1/{$portal}/cashbooks/close", ['cashbook_id' => $cashbook->id])
            ->assertCreated()
            ->assertJsonPath('data.opening_balance', '1200.00')
            ->assertJsonPath('data.closing_balance', '1300.00');
        $this->assertDatabaseHas('cashbook_closings', ['cashbook_id' => $cashbook->id, 'closed_at' => '2026-10-22 21:30:00', 'closing_balance' => 1200]);
        $this->assertDatabaseCount('cashbook_closings', 2);
    }

    public function test_duplicate_daily_close_returns_422_without_overwriting_the_snapshot(): void
    {
        $this->freezeTime();
        $this->authenticate('admin');
        $cashbook = $this->cashbook();
        $this->postJson('/api/v1/admin/cashbooks/close', ['cashbook_id' => $cashbook->id])->assertCreated();
        $this->postTransaction($cashbook, 'income', 100);

        $this->postJson('/api/v1/admin/cashbooks/close', ['cashbook_id' => $cashbook->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['closing_date' => 'This cashbook has already been closed today.']);

        $this->assertDatabaseCount('cashbook_closings', 1);
        $this->assertDatabaseHas('cashbook_closings', ['cashbook_id' => $cashbook->id, 'closing_balance' => 1000]);
        $this->assertDatabaseHas('cashbooks', ['id' => $cashbook->id, 'current_balance' => 1100]);
    }

    #[DataProvider('portals')]
    public function test_it_lists_only_the_selected_cashbook_closings_including_inactive_history(string $portal): void
    {
        $this->authenticate($portal);
        $cashbook = $this->cashbook(['is_active' => false]);
        CashbookClosing::factory()->for($cashbook)->create(['closing_date' => '2026-10-21']);
        $latest = CashbookClosing::factory()->for($cashbook)->create(['closing_date' => '2026-10-22']);
        CashbookClosing::factory()->create();

        $this->getJson("/api/v1/{$portal}/cashbooks/{$cashbook->id}/closings?page=1&per_page=1")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $latest->id)->assertJsonPath('meta.total', 2);
        $this->getJson("/api/v1/{$portal}/cashbooks/{$cashbook->id}/closings")
            ->assertOk()->assertJsonCount(2, 'data');
    }

    #[DataProvider('portals')]
    public function test_it_filters_closing_dates_with_inclusive_bounds_and_pagination(string $portal): void
    {
        $this->authenticate($portal);
        $cashbook = $this->cashbook();
        CashbookClosing::factory()->for($cashbook)->create(['closing_date' => '2026-10-20']);
        $first = CashbookClosing::factory()->for($cashbook)->create(['closing_date' => '2026-10-21']);
        $last = CashbookClosing::factory()->for($cashbook)->create(['closing_date' => '2026-10-22']);
        CashbookClosing::factory()->for($cashbook)->create(['closing_date' => '2026-10-23']);
        $url = "/api/v1/{$portal}/cashbooks/{$cashbook->id}/closings";

        $this->getJson($url.'?date_from=2026-10-21&date_to=2026-10-22&page=1&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $last->id)->assertJsonPath('meta.total', 2);
        $this->getJson($url.'?date_from=2026-10-21&date_to=2026-10-22&page=2&per_page=1')
            ->assertOk()->assertJsonPath('data.0.id', $first->id)->assertJsonPath('meta.total', 2);
        $this->getJson($url.'?date_from=2026-10-22')
            ->assertOk()->assertJsonCount(2, 'data');
        $this->getJson($url.'?date_to=2026-10-21')
            ->assertOk()->assertJsonCount(2, 'data');
        $this->getJson($url.'?date_from=2026-10-21&date_to=2026-10-21')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id);
        $this->getJson($url.'?date_from=2026-10-24')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    #[DataProvider('invalidClosingDateFilters')]
    public function test_closing_list_returns_422_for_invalid_dates(array $filters, string $field): void
    {
        $this->authenticate('admin');
        $cashbook = $this->cashbook();

        $this->getJson("/api/v1/admin/cashbooks/{$cashbook->id}/closings?".http_build_query($filters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function invalidClosingDateFilters(): array
    {
        return [
            'invalid start' => [['date_from' => 'invalid'], 'date_from'],
            'invalid end' => [['date_to' => '2026-02-30'], 'date_to'],
            'reversed range' => [['date_from' => '2026-10-22', 'date_to' => '2026-10-21'], 'date_to'],
        ];
    }

    public function test_inactive_cashbook_returns_422_without_creating_a_closing(): void
    {
        $this->authenticate('admin');
        $cashbook = $this->cashbook(['is_active' => false]);

        $this->postJson('/api/v1/admin/cashbooks/close', ['cashbook_id' => $cashbook->id])
            ->assertUnprocessable()->assertJsonValidationErrors('cashbook_id');

        $this->assertDatabaseCount('cashbook_closings', 0);
    }

    public function test_pos_returns_404_for_non_pos_cashbooks(): void
    {
        $this->authenticate('pos');
        $cashbook = $this->cashbook(['type' => 'cash']);

        $this->postJson('/api/v1/pos/cashbooks/close', ['cashbook_id' => $cashbook->id])->assertNotFound();
        $this->getJson("/api/v1/pos/cashbooks/{$cashbook->id}/closings")->assertNotFound();

        $this->assertDatabaseCount('cashbook_closings', 0);
    }

    #[DataProvider('portals')]
    public function test_missing_cashbook_returns_422_for_close_and_404_for_history(string $portal): void
    {
        $this->authenticate($portal);

        $this->postJson("/api/v1/{$portal}/cashbooks/close", ['cashbook_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('cashbook_id');
        $this->getJson("/api/v1/{$portal}/cashbooks/999999/closings")->assertNotFound();
    }

    #[DataProvider('portals')]
    public function test_endpoints_return_401_without_authentication(string $portal): void
    {
        $cashbook = $this->cashbook();

        $this->postJson("/api/v1/{$portal}/cashbooks/close", ['cashbook_id' => $cashbook->id])->assertUnauthorized();
        $this->getJson("/api/v1/{$portal}/cashbooks/{$cashbook->id}/closings")->assertUnauthorized();
    }

    #[DataProvider('portals')]
    public function test_endpoints_return_403_without_portal_access(string $portal): void
    {
        $this->authenticate($portal === 'admin' ? 'pos' : 'admin');
        $cashbook = $this->cashbook();

        $this->postJson("/api/v1/{$portal}/cashbooks/close", ['cashbook_id' => $cashbook->id])->assertForbidden();
        $this->getJson("/api/v1/{$portal}/cashbooks/{$cashbook->id}/closings")->assertForbidden();

        $this->assertDatabaseCount('cashbook_closings', 0);
    }

    public function test_admin_endpoints_return_403_without_the_required_role(): void
    {
        $user = User::factory()->create(['role' => UserRoleEnum::RestaurantAdministrator, 'portal_access' => UserPortalAccessEnum::Admin]);
        $this->actingAs($user, 'sanctum');
        $cashbook = $this->cashbook();

        $this->postJson('/api/v1/admin/cashbooks/close', ['cashbook_id' => $cashbook->id])->assertForbidden();
        $this->getJson("/api/v1/admin/cashbooks/{$cashbook->id}/closings")->assertForbidden();
        $this->assertDatabaseCount('cashbook_closings', 0);
    }

    public function test_invalid_inputs_return_422(): void
    {
        $this->authenticate('admin');
        $cashbook = $this->cashbook();

        $this->postJson('/api/v1/admin/cashbooks/close', ['cashbook_id' => $cashbook->id, 'remark' => ['invalid']])
            ->assertUnprocessable()->assertJsonValidationErrors('remark');
        $this->postJson('/api/v1/admin/cashbooks/close', ['cashbook_id' => $cashbook->id, 'remark' => str_repeat('a', 2001)])
            ->assertUnprocessable()->assertJsonValidationErrors('remark');
        $this->getJson("/api/v1/admin/cashbooks/{$cashbook->id}/closings?page=0&per_page=101")
            ->assertUnprocessable()->assertJsonValidationErrors(['page', 'per_page']);
        $this->assertDatabaseCount('cashbook_closings', 0);
    }

    public function test_cashbook_with_a_closing_cannot_be_deleted_or_have_its_opening_balance_changed(): void
    {
        $this->authenticate('admin');
        $cashbook = $this->cashbook();
        $this->postJson('/api/v1/admin/cashbooks/close', ['cashbook_id' => $cashbook->id])->assertCreated();

        $this->deleteJson("/api/v1/admin/cashbooks/{$cashbook->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('id');
        $this->postJson('/api/v1/admin/cashbooks', [
            'id' => $cashbook->id,
            'code' => $cashbook->code,
            'name' => $cashbook->name,
            'type' => 'pos_cash',
            'opening_balance' => 2000,
        ])->assertUnprocessable()->assertJsonValidationErrors('opening_balance');

        $this->assertDatabaseHas('cashbooks', ['id' => $cashbook->id, 'opening_balance' => 1000]);
        $this->assertDatabaseCount('cashbook_closings', 1);
    }

    #[DataProvider('invalidCashbookIds')]
    public function test_close_returns_422_for_missing_or_invalid_cashbook_id(array $payload): void
    {
        $this->authenticate('admin');

        $this->postJson('/api/v1/admin/cashbooks/close', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('cashbook_id');

        $this->assertDatabaseCount('cashbook_closings', 0);
    }

    public static function invalidCashbookIds(): array
    {
        return [
            'missing' => [[]],
            'null' => [['cashbook_id' => null]],
            'invalid' => [['cashbook_id' => 'invalid']],
        ];
    }

    public static function portals(): array
    {
        return ['admin' => ['admin'], 'pos' => ['pos']];
    }

    private function authenticate(string $portal): User
    {
        $user = User::factory()->create([
            'role' => UserRoleEnum::HotelAdministrator,
            'portal_access' => $portal === 'admin' ? UserPortalAccessEnum::Admin : UserPortalAccessEnum::Pos,
        ]);
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function cashbook(array $overrides = []): Cashbook
    {
        return Cashbook::query()->create(array_merge([
            'code' => fake()->unique()->bothify('CB-########'),
            'name' => 'POS Cash',
            'type' => 'pos_cash',
            'opening_balance' => 1000,
            'is_active' => true,
        ], $overrides));
    }

    private function postTransaction(Cashbook $cashbook, string $type, int $amount): void
    {
        app(CashbookTransactionService::class)->post([
            'cashbook_id' => $cashbook->id,
            'transaction_type' => $type,
            'amount' => $amount,
            'transaction_date' => now()->toDateTimeString(),
        ]);
    }
}
