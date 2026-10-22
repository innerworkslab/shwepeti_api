<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\Cashbook;
use App\Models\CashbookTransaction;
use App\Models\CashbookTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CashbookTransferModuleTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('portals')]
    public function test_it_posts_linked_entries_and_preserves_total_balance(string $portal): void
    {
        $payload = $this->payload();
        $this->authenticate($portal);

        $response = $this->postJson($this->url($portal), $payload)->assertCreated()
            ->assertJsonPath('data.amount', '25.50')->assertJsonCount(2, 'data.transactions')
            ->assertJsonPath('data.transactions.0.transaction_type', 'expense')
            ->assertJsonPath('data.transactions.1.transaction_type', 'income')
            ->assertJsonPath('data.transactions.0.balance_after', '974.50')
            ->assertJsonPath('data.transactions.1.balance_after', '25.50');

        $id = $response->json('data.id');
        $this->assertDatabaseHas('cashbook_transactions', ['reference_type' => 'cashbook_transfer', 'reference_id' => $id, 'cashbook_id' => $payload['from_cashbook_id'], 'amount' => '25.50']);
        $this->assertDatabaseHas('cashbooks', ['id' => $payload['from_cashbook_id'], 'current_balance' => '974.50']);
        $this->assertDatabaseHas('cashbooks', ['id' => $payload['to_cashbook_id'], 'current_balance' => '25.50']);
        $this->getJson($this->url($portal).'/'.$id)->assertOk()->assertJsonCount(2, 'data.transactions');
        $this->getJson('/api/v1/'.$portal.'/cashbook-transactions?cashbook_id='.$payload['from_cashbook_id'])
            ->assertOk()->assertJsonPath('closing_balance', '974.50');
        $this->postJson($this->url($portal), $payload)->assertUnprocessable()->assertJsonValidationErrors('request_id');
        $this->assertDatabaseCount('cashbook_transfers', 1);
        $this->assertDatabaseCount('cashbook_transactions', 2);
    }

    public static function portals(): array
    {
        return [['admin'], ['pos']];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_transfers_return_422_without_changing_balances(array $overrides, string $field): void
    {
        $payload = array_replace($this->payload(), $overrides);
        $this->authenticate('admin');

        $this->postJson($this->url('admin'), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('cashbook_transfers', 0);
        $this->assertDatabaseCount('cashbook_transactions', 0);
        $this->assertDatabaseHas('cashbooks', ['id' => $payload['from_cashbook_id'], 'current_balance' => '1000.00']);
    }

    public static function invalidPayloads(): array
    {
        return [
            'zero' => [['amount' => '0'], 'amount'],
            'negative' => [['amount' => '-1'], 'amount'],
            'precision' => [['amount' => '1.001'], 'amount'],
            'insufficient' => [['amount' => '1000.01'], 'amount'],
            'overflow' => [['amount' => '10000000000000000'], 'amount'],
            'exponent' => [['amount' => '1e2'], 'amount'],
            'date' => [['transfer_date' => 'invalid'], 'transfer_date'],
            'missing request id' => [['request_id' => null], 'request_id'],
            'invalid request id' => [['request_id' => 'invalid'], 'request_id'],
            'missing destination' => [['to_cashbook_id' => 999999], 'to_cashbook_id'],
        ];
    }

    public function test_same_cashbook_and_inactive_cashbooks_return_422(): void
    {
        $payload = $this->payload();
        $this->authenticate('admin');
        $this->postJson($this->url('admin'), array_replace($payload, ['to_cashbook_id' => $payload['from_cashbook_id']]))
            ->assertUnprocessable()->assertJsonValidationErrors('to_cashbook_id');

        foreach (['from_cashbook_id', 'to_cashbook_id'] as $field) {
            Cashbook::query()->whereKey($payload[$field])->update(['is_active' => false]);
            $label = $field === 'from_cashbook_id' ? 'source' : 'destination';
            $this->postJson($this->url('admin'), $payload)->assertUnprocessable()
                ->assertJsonValidationErrors([$field => "The selected {$label} cashbook is inactive. Choose an active cashbook."]);
            Cashbook::query()->whereKey($payload[$field])->update(['is_active' => true]);
        }
        $this->assertDatabaseCount('cashbook_transactions', 0);
        $this->assertDatabaseCount('cashbook_transfers', 0);
    }

    #[DataProvider('mainCashbookTypes')]
    public function test_pos_cannot_transfer_or_read_transfers_from_main_cashbooks(string $sourceType): void
    {
        $payload = $this->payload();
        Cashbook::query()->whereKey($payload['from_cashbook_id'])->update(['type' => $sourceType]);
        $this->authenticate('admin');
        $id = $this->postJson($this->url('admin'), $payload)->assertCreated()->json('data.id');
        $this->authenticate('pos');

        $this->postJson($this->url('pos'), array_replace($payload, ['request_id' => fake()->uuid()]))
            ->assertUnprocessable()->assertJsonValidationErrors([
                'from_cashbook_id' => 'POS transfers must start from a POS Cash or POS Bank cashbook. Choose a POS cashbook as the source.',
            ]);
        $this->getJson($this->url('pos').'/'.$id)->assertNotFound();
        $this->getJson($this->url('pos').'?cashbook_id='.$payload['to_cashbook_id'])->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('cashbook_transactions', 2);
    }

    public static function mainCashbookTypes(): array
    {
        return [['cash'], ['bank']];
    }

    #[DataProvider('allowedCashbookTypes')]
    public function test_it_transfers_to_all_cashbook_types_and_exposes_the_transfer(string $portal, string $sourceType, string $destinationType): void
    {
        $payload = $this->payload();
        Cashbook::query()->whereKey($payload['from_cashbook_id'])->update(['type' => $sourceType]);
        Cashbook::query()->whereKey($payload['to_cashbook_id'])->update(['type' => $destinationType]);
        $this->authenticate($portal);

        $id = $this->postJson($this->url($portal), $payload)->assertCreated()->json('data.id');

        $this->assertDatabaseHas('cashbooks', ['id' => $payload['from_cashbook_id'], 'current_balance' => '974.50']);
        $this->assertDatabaseHas('cashbooks', ['id' => $payload['to_cashbook_id'], 'current_balance' => '25.50']);
        $this->assertDatabaseCount('cashbook_transactions', 2);
        $this->getJson($this->url($portal).'/'.$id)->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson($this->url($portal).'?cashbook_id='.$payload['to_cashbook_id'])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
    }

    public static function allowedCashbookTypes(): array
    {
        $cases = [];
        foreach (['cash', 'bank', 'pos_cash', 'pos_bank'] as $sourceType) {
            foreach (['cash', 'bank', 'pos_cash', 'pos_bank'] as $destinationType) {
                $cases["admin {$sourceType} to {$destinationType}"] = ['admin', $sourceType, $destinationType];
                if (in_array($sourceType, ['pos_cash', 'pos_bank'], true)) {
                    $cases["pos {$sourceType} to {$destinationType}"] = ['pos', $sourceType, $destinationType];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('portals')]
    public function test_it_filters_and_paginates_transfers_including_inactive_history(string $portal): void
    {
        $payload = $this->payload();
        $this->authenticate($portal);
        $response = $this->postJson($this->url($portal), $payload)->assertCreated();
        $this->postJson($this->url($portal), array_replace($payload, ['request_id' => fake()->uuid(), 'transfer_date' => '2026-10-21']))->assertCreated();
        Cashbook::query()->whereKey($payload['from_cashbook_id'])->update(['is_active' => false]);

        $this->getJson($this->url($portal).'?'.http_build_query([
            'cashbook_id' => $payload['to_cashbook_id'], 'date_from' => '2026-10-22', 'date_to' => '2026-10-22',
            'search' => $response->json('data.reference_no'), 'page' => 1, 'perPage' => 1,
        ]))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $response->json('data.id'));
        $this->getJson($this->url($portal).'?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson($this->url($portal).'?date_from=2026-10-22&date_to=2026-10-21')->assertUnprocessable()->assertJsonValidationErrors('date_to');
    }

    public function test_failed_second_entry_rolls_back_transfer_and_both_balances(): void
    {
        $payload = $this->payload();
        $this->authenticate('admin');
        Event::listen('eloquent.creating: '.CashbookTransaction::class, function (CashbookTransaction $transaction): void {
            if ($transaction->transaction_type->value === 'income') {
                throw new \RuntimeException('Simulated ledger write failure');
            }
        });

        $this->postJson($this->url('admin'), $payload)->assertInternalServerError();

        $this->assertDatabaseCount('cashbook_transfers', 0);
        $this->assertDatabaseCount('cashbook_transactions', 0);
        $this->assertDatabaseHas('cashbooks', ['id' => $payload['from_cashbook_id'], 'current_balance' => '1000.00']);
        $this->assertDatabaseHas('cashbooks', ['id' => $payload['to_cashbook_id'], 'current_balance' => '0.00']);
    }

    public function test_exact_decimal_arithmetic_and_destination_limit(): void
    {
        $payload = $this->payload();
        $this->authenticate('admin');
        Cashbook::query()->whereKey($payload['from_cashbook_id'])->update(['current_balance' => '9999999999999999.99']);
        $this->postJson($this->url('admin'), array_replace($payload, ['amount' => '0.01']))->assertCreated()
            ->assertJsonPath('data.transactions.0.balance_after', '9999999999999999.98');
        Cashbook::query()->whereKey($payload['to_cashbook_id'])->update(['current_balance' => '9999999999999999.99']);
        $this->postJson($this->url('admin'), array_replace($payload, ['request_id' => fake()->uuid(), 'amount' => '0.01']))
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertDatabaseCount('cashbook_transfers', 1);
    }

    #[DataProvider('portals')]
    public function test_endpoints_require_authentication_and_portal_access(string $portal): void
    {
        $this->getJson($this->url($portal))->assertUnauthorized();
        $this->getJson($this->url($portal).'/1')->assertUnauthorized();
        $this->postJson($this->url($portal), [])->assertUnauthorized();
        $this->authenticate($portal === 'admin' ? 'pos' : 'admin');
        $this->getJson($this->url($portal))->assertForbidden();
        $this->postJson($this->url($portal), [])->assertForbidden();
    }

    public function test_admin_requires_hotel_administrator_role(): void
    {
        $user = User::factory()->create(['portal_access' => 'admin', 'role' => UserRoleEnum::RestaurantAdministrator]);
        $this->actingAs($user, 'sanctum')->postJson($this->url('admin'), [])->assertForbidden();
    }

    private function authenticate(string $portal): void
    {
        $this->actingAs(User::factory()->create([
            'portal_access' => $portal,
            'role' => $portal === 'admin' ? UserRoleEnum::HotelAdministrator : UserRoleEnum::RestaurantAdministrator,
        ]), 'sanctum');
    }

    private function url(string $portal): string
    {
        return '/api/v1/'.$portal.'/cashbook-transfers';
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return CashbookTransfer::factory()->make()->only(['request_id', 'from_cashbook_id', 'to_cashbook_id', 'amount', 'transfer_date', 'remark']);
    }
}
