<?php

namespace Tests\Feature;

use App\Enums\CashbookTypeEnum;
use App\Enums\UserPortalAccessEnum;
use App\Enums\UserRoleEnum;
use App\Models\Cashbook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCashbookModuleTest extends TestCase
{
    use RefreshDatabase;

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
        $user = User::factory()->create([
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
