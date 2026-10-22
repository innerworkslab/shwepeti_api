<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Cashbook;
use App\Models\CashbookTransaction;
use App\Models\CashbookTransfer;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\FoodOrder;
use App\Models\FoodOrderItem;
use App\Models\InventoryLedger;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\ServiceOrder;
use App\Models\StockIn;
use App\Models\SupplierApPayment;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProfitAndLossReportTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/reports/profit-and-loss';

    private const PERIOD = '?date_from=2026-10-01&date_to=2026-10-31';

    public function test_returns_revenue_expenses_and_profit_without_double_counting(): void
    {
        $booking = $this->booking();
        $food = FoodOrder::query()->create(['booking_id' => $booking->id, 'order_no' => 'FO-1', 'status' => 'served', 'subtotal' => 50, 'discount_amount' => 5, 'service_charge_amount' => 2, 'tax_amount' => 3, 'total_amount' => 50]);
        ServiceOrder::query()->create(['booking_id' => $booking->id, 'order_no' => 'SO-1', 'status' => 'completed', 'total_amount' => 22, 'tax_amount' => 2]);
        FoodOrder::query()->create(['booking_id' => $booking->id, 'order_no' => 'FO-CANCELLED', 'status' => 'cancelled', 'total_amount' => 900]);
        ServiceOrder::query()->create(['booking_id' => $booking->id, 'order_no' => 'SO-CANCELLED', 'status' => 'cancelled', 'total_amount' => 900]);
        $this->cashEntry('income', 10);
        $this->cashEntry('expense', 20);
        foreach ([BookingPayment::class, CashbookTransfer::class, FixedAsset::class, SupplierApPayment::class, StockIn::class] as $reference) {
            $this->cashEntry('income', 1000, $reference);
            $this->cashEntry('expense', 1000, $reference);
        }
        FixedAssetDepreciation::factory()->create(['period' => '2026-10-01', 'amount' => 5]);
        FixedAssetDepreciation::factory()->create(['period' => '2026-11-01', 'amount' => 500]);
        $stockCost = $this->inventoryEntry(['total_cost' => 12]);
        $foodItem = $this->foodItem($food);
        $stockCost->replicate()->fill(['transaction_type' => 'food_order', 'reference_type' => (new FoodOrderItem)->getMorphClass(), 'reference_id' => $foodItem->id, 'total_cost' => 8, 'transaction_date' => '2026-09-30 12:00:00'])->save();
        $stockCost->replicate()->fill(['transaction_type' => 'transfer_out', 'total_cost' => null])->save();
        $stockCost->replicate()->fill(['transaction_type' => 'in', 'total_cost' => 500])->save();
        $stockCost->replicate()->fill(['transaction_type' => 'adjustment_subtract', 'total_cost' => null])->save();
        $stockCost->replicate()->fill(['transaction_date' => '2026-11-01 00:00:00', 'total_cost' => null])->save();
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]));

        $this->getJson(self::ENDPOINT.self::PERIOD)->assertOk()
            ->assertJsonPath('data.revenue', ['rooms' => '95.00', 'food' => '47.00', 'services' => '20.00', 'other_income' => '10.00', 'total' => '172.00'])
            ->assertJsonPath('data.cost_of_sales.total', '20.00')
            ->assertJsonPath('data.expenses.total', '25.00')
            ->assertJsonPath('data.gross_profit', '152.00')
            ->assertJsonPath('data.net_profit', '127.00')
            ->assertJsonPath('data.has_missing_costs', false);
    }

    public function test_uses_inclusive_checkout_dates_and_preserves_archived_revenue(): void
    {
        $this->booking(['checked_out_at' => '2026-10-01 00:00:00'])->delete();
        $this->booking(['checked_out_at' => '2026-10-31 23:59:59']);
        $this->booking(['checked_out_at' => '2026-09-30 23:59:59']);
        $this->booking(['checked_out_at' => '2026-11-01 00:00:00']);
        $this->booking(['checked_out_at' => null, 'status' => 'checked_in']);
        $this->booking(['status' => 'cancelled']);
        $this->cashEntry('income', 10, date: '2026-10-31 23:59:59');
        $this->cashEntry('income', 500, date: '2026-11-01 00:00:00');
        $this->cashEntry('expense', 500, date: '2026-09-30 23:59:59');
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]));

        $this->getJson(self::ENDPOINT.self::PERIOD)->assertOk()
            ->assertJsonPath('data.revenue.rooms', '190.00')
            ->assertJsonPath('data.net_profit', '200.00');
    }

    #[TestWith(['out'])]
    #[TestWith(['food_order'])]
    public function test_missing_inventory_costs_do_not_present_final_profit(string $type): void
    {
        $booking = $this->booking();
        $food = FoodOrder::query()->create(['booking_id' => $booking->id, 'order_no' => 'FO-1', 'status' => 'served', 'total_amount' => 5]);
        $foodItem = $this->foodItem($food);
        $this->inventoryEntry(['transaction_type' => $type, 'reference_type' => (new FoodOrderItem)->getMorphClass(), 'reference_id' => $foodItem->id, 'total_cost' => null]);
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]));

        $this->getJson(self::ENDPOINT.self::PERIOD)->assertOk()
            ->assertJsonPath('data.has_missing_costs', true)
            ->assertJsonPath('data.cost_of_sales.missing_cost_count', 1)
            ->assertJsonPath('data.cost_of_sales.total', null)
            ->assertJsonPath('data.gross_profit', null)
            ->assertJsonPath('data.net_profit', null)
            ->assertJsonPath('data.profit_before_unrecorded_costs', '100.00');
    }

    public function test_empty_period_returns_zero_totals(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]));

        $this->getJson(self::ENDPOINT.self::PERIOD)->assertOk()
            ->assertJsonPath('data.revenue.total', '0.00')
            ->assertJsonPath('data.expenses.total', '0.00')
            ->assertJsonPath('data.net_profit', '0.00')
            ->assertJsonPath('data.has_missing_costs', false);
    }

    public function test_losses_are_returned_as_negative_amounts(): void
    {
        $this->cashEntry('expense', 20.25);
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]));

        $this->getJson(self::ENDPOINT.self::PERIOD)->assertOk()->assertJsonPath('data.net_profit', '-20.25');
    }

    #[TestWith(['', ['date_from', 'date_to']])]
    #[TestWith(['?date_from=invalid&date_to=2026-10-31', ['date_from']])]
    #[TestWith(['?date_from=2026-10-01&date_to=2026-02-30', ['date_to']])]
    #[TestWith(['?date_from=2026-10-31&date_to=2026-10-01', ['date_to']])]
    public function test_invalid_period_returns_422(string $query, array $fields): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]));

        $this->getJson(self::ENDPOINT.$query)->assertUnprocessable()->assertJsonValidationErrors($fields);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson(self::ENDPOINT.self::PERIOD)->assertUnauthorized();
    }

    #[TestWith(['inventory_administrator'])]
    #[TestWith(['front_office_administrator'])]
    #[TestWith(['reservation_administrator'])]
    #[TestWith(['restaurant_administrator'])]
    #[TestWith(['customer_service_administrator'])]
    public function test_other_roles_receive_403(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));

        $this->getJson(self::ENDPOINT.self::PERIOD)->assertForbidden();
    }

    /** @param array<string, mixed> $overrides */
    private function booking(array $overrides = []): Booking
    {
        $category = RoomCategory::query()->create(['name' => 'Standard']);
        $room = Room::query()->create(['name' => fake()->unique()->numerify('Room ####'), 'room_category_id' => $category->id, 'price' => 100, 'status' => 'available']);

        return Booking::query()->create(array_replace([
            'booking_no' => fake()->unique()->bothify('BK-########'), 'room_id' => $room->id, 'guest_name' => 'Guest',
            'expected_check_in_at' => '2026-09-30 12:00:00', 'expected_check_out_at' => '2026-10-01 12:00:00',
            'checked_out_at' => '2026-10-01 12:00:00', 'status' => 'checked_out',
            'subtotal' => 100, 'discount_amount' => 10, 'service_charge_amount' => 5, 'tax_amount' => 10, 'total_amount' => 105,
        ], $overrides));
    }

    private function cashEntry(string $type, float $amount, ?string $reference = null, string $date = '2026-10-01 00:00:00'): void
    {
        $cashbook = Cashbook::query()->create(['code' => fake()->unique()->bothify('CB-########'), 'name' => 'Main Cash', 'type' => 'cash']);
        CashbookTransaction::query()->create([
            'reference_no' => fake()->unique()->bothify('CBT-########'), 'cashbook_id' => $cashbook->id,
            'transaction_type' => $type, 'amount' => $amount, 'balance_after' => 0,
            'transaction_date' => $date, 'reference_type' => $reference, 'reference_id' => $reference ? 1 : null,
        ]);
    }

    private function foodItem(FoodOrder $food): FoodOrderItem
    {
        $category = MenuCategory::query()->create(['name' => 'Food', 'slug' => 'food', 'code' => 'FOOD']);
        $warehouse = Warehouse::query()->create(['code' => 'KITCHEN', 'name' => 'Kitchen']);
        $menu = Menu::query()->create(['warehouse_id' => $warehouse->id, 'menu_category_id' => $category->id, 'name' => 'Meal', 'code' => 'MEAL', 'price' => 50]);

        return $food->items()->create(['menu_id' => $menu->id, 'qty' => 1, 'unit_price' => 50, 'total' => 50]);
    }

    /** @param array<string, mixed> $overrides */
    private function inventoryEntry(array $overrides = []): InventoryLedger
    {
        $warehouse = Warehouse::query()->create(['code' => 'MAIN', 'name' => 'Main Warehouse']);
        $group = UnitGroup::query()->create(['name' => 'Quantity', 'slug' => 'quantity']);
        $unit = Unit::query()->create(['unit_group_id' => $group->id, 'name' => 'Piece', 'symbol' => 'pc']);
        $category = ItemCategory::query()->create(['name' => 'Supplies', 'slug' => 'supplies', 'code' => 'SUPPLIES']);
        $item = Item::query()->create(['item_category_id' => $category->id, 'name' => 'Supply', 'code' => 'SUPPLY', 'stock_unit_id' => $unit->id]);

        return InventoryLedger::query()->create(array_replace([
            'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'unit_id' => $unit->id,
            'transaction_type' => 'out', 'quantity' => 1, 'base_quantity' => -1, 'balance_quantity' => 0,
            'total_cost' => 10, 'transaction_date' => '2026-10-31 23:59:59',
        ], $overrides));
    }
}
