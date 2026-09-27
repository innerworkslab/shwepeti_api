<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierApBalanceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_outstanding_supplier_balances_and_can_include_zero_balances(): void
    {
        $headers = $this->authHeaders();
        $outstanding = Supplier::factory()->wholesale()->create(['credit_limit_amount' => 1000]);
        $outstanding->forceFill(['payable_balance' => 400])->save();
        Supplier::factory()->retail()->create(['payable_balance' => 0]);

        $this->withHeaders($headers)->getJson('/api/v1/admin/supplier-ap-balances?page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.supplier_id', $outstanding->id)
            ->assertJsonPath('data.0.payable_balance', '400.00')
            ->assertJsonPath('data.0.available_credit_amount', '600.00');

        $this->withHeaders($headers)->getJson('/api/v1/admin/supplier-ap-balances?include_zero=1&page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
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
