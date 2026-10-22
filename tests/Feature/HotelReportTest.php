<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cashbook;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\FoodOrder;
use App\Models\InventoryLedger;
use App\Models\InventoryStockBalance;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\ServiceOrder;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class HotelReportTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/admin/reports/';

    #[TestWith(['daily-summary'])]
    #[TestWith(['room-occupancy'])]
    #[TestWith(['room-revenue'])]
    #[TestWith(['guest-balances'])]
    #[TestWith(['food-sales'])]
    #[TestWith(['top-selling-items'])]
    #[TestWith(['other-bills'])]
    #[TestWith(['inventory-balance'])]
    #[TestWith(['low-stock'])]
    #[TestWith(['depreciation'])]
    public function test_reports_require_authentication_and_hotel_administrator_access(string $report): void
    {
        $this->getJson(self::BASE.$report)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'front_office_administrator']))
            ->getJson(self::BASE.$report)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'hotel_administrator']))
            ->getJson(self::BASE.$report.'?date=2026-10-22')->assertOk()
            ->assertJsonPath('meta.report', $report)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Summary Report retrieved successfully.')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']])
            ->assertJsonMissingPath('meta.notes')
            ->assertJsonMissingPath('data.rows')
            ->assertJsonMissingPath('data.pagination');
    }

    #[TestWith(['date=invalid', 'date'])]
    #[TestWith(['date=2026-02-30', 'date'])]
    #[TestWith(['date=2026-10-22&date_from=2026-10-01&date_to=2026-10-31', 'date'])]
    #[TestWith(['date_to=2026-10-22', 'date_to'])]
    #[TestWith(['date_from=2026-10-23&date_to=2026-10-22', 'date_to'])]
    #[TestWith(['date_from=2024-01-01&date_to=2026-10-22', 'date_to'])]
    #[TestWith(['room_id=999', 'room_id'])]
    #[TestWith(['room_category_id=999', 'room_category_id'])]
    #[TestWith(['warehouse_id=999', 'warehouse_id'])]
    #[TestWith(['item_category_id=999', 'item_category_id'])]
    #[TestWith(['page=0', 'page'])]
    #[TestWith(['per_page=101', 'per_page'])]
    public function test_invalid_filters_return_422(string $query, string $field): void
    {
        $this->admin();

        $this->getJson(self::BASE.'room-revenue?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_empty_report_returns_an_array_and_top_level_pagination(): void
    {
        $this->admin();

        $this->getJson(self::BASE.'room-revenue?date=2026-10-22')->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('meta.summary.total_room_revenue', '0.00');
    }

    #[TestWith([[]])]
    #[TestWith([['date_from' => null, 'date_to' => null]])]
    #[TestWith([['date_from' => '', 'date_to' => '']])]
    public function test_empty_date_range_defaults_to_today(array $filters): void
    {
        $this->admin();

        $this->json('GET', self::BASE.'daily-summary', $filters)->assertOk()
            ->assertJsonPath('meta.date_from', '2026-10-23')
            ->assertJsonPath('meta.date_to', '2026-10-23')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-10-23');
    }

    #[TestWith([['date_from' => '2026-10-22', 'date_to' => null], '2026-10-22', '2026-10-23'])]
    #[TestWith([['date_from' => null, 'date_to' => '2026-10-24'], '2026-10-23', '2026-10-24'])]
    public function test_only_empty_date_boundary_defaults_to_today(array $filters, string $from, string $to): void
    {
        $this->admin();

        $this->json('GET', self::BASE.'daily-summary', $filters)->assertOk()
            ->assertJsonPath('meta.date_from', $from)
            ->assertJsonPath('meta.date_to', $to)
            ->assertJsonCount(2, 'data');
    }

    public function test_room_revenue_uses_checkout_boundaries_filters_archived_rooms_and_full_totals(): void
    {
        $this->admin();
        $first = $this->booking(['checked_out_at' => '2026-10-22 00:00:00', 'total_amount' => 110]);
        $second = $this->booking(['room_id' => $first->room_id, 'checked_out_at' => '2026-10-22 23:59:59', 'total_amount' => 220]);
        $this->booking(['room_id' => $first->room_id, 'checked_out_at' => '2026-10-23 00:00:00', 'total_amount' => 999]);
        $this->booking(['room_id' => $first->room_id, 'status' => 'cancelled', 'total_amount' => 999]);
        $this->booking(['total_amount' => 999]);
        $category = $first->room->room_category_id;
        $first->room->delete();
        $first->delete();

        $this->getJson(self::BASE.'room-revenue?date=2026-10-22&room_id='.$first->room_id.'&room_category_id='.$category.'&per_page=1&page=2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.booking_id', $second->id)
            ->assertJsonPath('data.0.extra_time', null)->assertJsonPath('meta.summary.total_room_revenue', '330.00')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_occupancy_includes_overlapping_stays_and_excludes_non_overlapping_or_cancelled_bookings(): void
    {
        $this->admin();
        $booking = $this->booking(['checked_in_at' => '2026-10-20 12:00:00', 'checked_out_at' => null, 'status' => 'checked_in']);
        $this->booking(['checked_out_at' => '2026-10-22 00:00:00']);
        $this->booking(['checked_in_at' => '2026-10-23 00:00:00', 'checked_out_at' => null, 'status' => 'checked_in']);
        $this->booking(['status' => 'cancelled']);

        $this->getJson(self::BASE.'room-occupancy?date=2026-10-22&room_category_id='.$booking->room->room_category_id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'occupied')
            ->assertJsonPath('data.0.duration_days', 3)->assertJsonPath('data.0.check_out', null);
    }

    public function test_guest_balances_include_all_charges_separate_deposits_and_deduct_refunds(): void
    {
        $this->admin();
        $booking = $this->booking(['status' => 'checked_in', 'checked_out_at' => null, 'total_amount' => 300]);
        $this->order($booking, ['total_amount' => 100]);
        $this->order($booking, ['status' => 'cancelled', 'total_amount' => 999]);
        ServiceOrder::query()->forceCreate(['booking_id' => $booking->id, 'order_no' => 'SO-1', 'total_amount' => 50]);
        ServiceOrder::query()->forceCreate(['booking_id' => $booking->id, 'order_no' => 'SO-X', 'total_amount' => 999, 'status' => 'cancelled']);
        $this->payment($booking, 'deposit', 100);
        $this->payment($booking, 'partial', 200);
        $this->payment($booking, 'refund', 20);
        $paid = $this->booking(['status' => 'checked_in', 'checked_out_at' => null, 'total_amount' => 50]);
        $this->payment($paid, 'partial', 50);
        $this->booking();

        $this->getJson(self::BASE.'guest-balances')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.total_charges', '450.00')->assertJsonPath('data.0.deposit', '100.00')
            ->assertJsonPath('data.0.paid', '200.00')->assertJsonPath('data.0.refund', '20.00')
            ->assertJsonPath('data.0.outstanding', '170.00')->assertJsonPath('data.0.payment_status', 'partial')
            ->assertJsonPath('data.1.payment_status', 'paid')->assertJsonPath('meta.summary.total_outstanding', '170.00');
    }

    public function test_food_sales_and_top_items_exclude_cancellations_and_reconcile_order_totals(): void
    {
        $this->admin();
        $booking = $this->booking();
        $food = $this->order($booking, ['subtotal' => 30000, 'discount_amount' => 1000, 'service_charge_amount' => 3000, 'tax_amount' => 2000, 'total_amount' => 34000]);
        $menu = $this->menu('Fried Rice', 'Food');
        $food->items()->create(['menu_id' => $menu->id, 'qty' => 2, 'unit_price' => 15000, 'total' => 30000]);
        $drink = $this->order($booking, ['total_amount' => 9000]);
        $coke = $this->menu('Coke', 'Drink');
        $drink->items()->create(['menu_id' => $coke->id, 'qty' => 3, 'unit_price' => 3000, 'total' => 9000]);
        foreach ([['status' => 'cancelled'], ['created_at' => '2026-10-23 00:00:00']] as $attributes) {
            $this->order($booking, $attributes)->items()->create(['menu_id' => $menu->id, 'qty' => 99, 'unit_price' => 15000, 'total' => 1485000]);
        }

        $this->getJson(self::BASE.'food-sales?date=2026-10-22&per_page=1')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.summary.item_sales', '39000.00')
            ->assertJsonPath('meta.summary.room_service', '3000.00')->assertJsonPath('meta.summary.total_order_revenue', '43000.00')
            ->assertJsonPath('meta.summary.category_sales.0.sales', '30000.00');
        $this->getJson(self::BASE.'top-selling-items?date=2026-10-22')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.item', 'Coke')->assertJsonPath('data.0.qty_sold', 3)
            ->assertJsonPath('data.1.revenue', '30000.00');
    }

    public function test_other_bills_return_non_food_line_items_with_guest_and_charge_type(): void
    {
        $this->admin();
        $booking = $this->booking();
        $item = $this->item();
        $order = ServiceOrder::query()->forceCreate(['room_id' => $booking->room_id, 'booking_id' => $booking->id, 'order_no' => 'LAUNDRY', 'type' => 'laundry', 'total_amount' => 15000, 'created_at' => '2026-10-22 12:00:00']);
        $order->items()->create(['item_id' => $item->id, 'qty' => 1, 'unit_price' => 15000, 'total' => 15000, 'note' => 'Laundry Service']);
        $cancelled = $order->replicate()->fill(['order_no' => 'CANCELLED', 'status' => 'cancelled']);
        $cancelled->save();
        $order->items->first()->replicate()->fill(['service_order_id' => $cancelled->id])->save();

        $this->getJson(self::BASE.'other-bills?date=2026-10-22')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.guest', 'Mg Mg')->assertJsonPath('data.0.charge_type', 'laundry')
            ->assertJsonPath('data.0.description', 'Laundry Service')->assertJsonPath('meta.summary.total_other_bill_revenue', '15000.00');
    }

    public function test_inventory_reconstructs_opening_movements_adjustments_and_warehouse_transfers(): void
    {
        $this->admin();
        $item = $this->item();
        $warehouse = Warehouse::query()->create(['name' => 'Main', 'code' => 'MAIN']);
        $other = Warehouse::query()->create(['name' => 'Kitchen', 'code' => 'KITCHEN']);
        $this->ledger($item, $warehouse, 'in', 50, '2026-10-21 23:59:59');
        $this->ledger($item, $warehouse, 'in', 20);
        $this->ledger($item, $warehouse, 'food_order', -15);
        $this->ledger($item, $warehouse, 'adjustment_subtract', -2);
        $this->ledger($item, $warehouse, 'transfer_out', -3);
        $this->ledger($item, $other, 'transfer_in', 3);
        $this->ledger($item, $warehouse, 'in', 900, '2026-10-23 00:00:00');

        $this->getJson(self::BASE.'inventory-balance?date=2026-10-22&warehouse_id='.$warehouse->id)->assertOk()
            ->assertJsonPath('data.0.opening', '50.000000')->assertJsonPath('data.0.stock_in', '20.000000')
            ->assertJsonPath('data.0.stock_out', '18.000000')->assertJsonPath('data.0.adjustment', '-2.000000')
            ->assertJsonPath('data.0.current_stock', '50.000000');
        $this->getJson(self::BASE.'inventory-balance?date=2026-10-22')->assertOk()->assertJsonPath('data.0.current_stock', '53.000000');
    }

    public function test_low_stock_includes_zero_stock_items_and_threshold_equality_but_not_inactive_items(): void
    {
        $this->admin();
        $item = $this->item(['min_stock' => 20]);
        $this->item(['name' => 'Tissue', 'min_stock' => 10]);
        $this->item(['is_active' => false]);
        $warehouse = Warehouse::query()->create(['name' => 'Main', 'code' => 'MAIN']);
        InventoryStockBalance::query()->create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 20]);
        $healthy = $this->item(['min_stock' => 10]);
        InventoryStockBalance::query()->create(['item_id' => $healthy->id, 'warehouse_id' => $warehouse->id, 'quantity' => 11]);

        $this->getJson(self::BASE.'low-stock')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'low_stock')->assertJsonPath('data.1.status', 'out_of_stock');
    }

    public function test_depreciation_uses_month_snapshots_not_current_asset_accumulation(): void
    {
        $this->admin();
        $asset = FixedAsset::factory()->create(['purchase_amount' => 1200000, 'accumulated_depreciation' => 500000]);
        FixedAssetDepreciation::factory()->create(['fixed_asset_id' => $asset->id, 'period' => '2026-10-01', 'amount' => 100000, 'opening_book_value' => 1000000, 'closing_book_value' => 900000]);
        FixedAssetDepreciation::factory()->create(['fixed_asset_id' => $asset->id, 'period' => '2026-11-01', 'amount' => 100000, 'closing_book_value' => 800000]);

        $this->getJson(self::BASE.'depreciation?date=2026-10-22')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.accumulated', '300000.00')->assertJsonPath('data.0.book_value', '900000.00')
            ->assertJsonPath('meta.summary.total_depreciation', '100000.00');
    }

    public function test_daily_summary_separates_revenue_receipts_refunds_and_outstanding(): void
    {
        $this->admin();
        $booking = $this->booking(['total_amount' => 300]);
        $this->order($booking, ['total_amount' => 100]);
        $this->order($booking, ['status' => 'cancelled', 'total_amount' => 999]);
        $this->payment($booking, 'deposit', 100, '2026-10-21 12:00:00');
        $this->payment($booking, 'checkout', 200);
        $this->payment($booking, 'refund', 20);
        $this->payment($booking, 'partial', 50, '2026-10-23 00:00:00');

        $this->getJson(self::BASE.'daily-summary?date=2026-10-22')->assertOk()
            ->assertJsonPath('data.0.total_rooms', 1)->assertJsonPath('data.0.available_rooms', 1)
            ->assertJsonPath('data.0.occupied_rooms', 0)->assertJsonPath('data.0.check_outs', 1)
            ->assertJsonPath('data.0.total_revenue', '400.00')->assertJsonPath('data.0.total_payment_received', '200.00')
            ->assertJsonPath('data.0.net_payment_received', '180.00')->assertJsonPath('data.0.outstanding_balance', '120.00');
        $this->getJson(self::BASE.'daily-summary?date_from=2026-10-21&date_to=2026-10-22')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_available_rooms_are_total_minus_occupied_for_past_and_current_dates(): void
    {
        $this->admin();
        $booking = $this->booking(['status' => 'checked_in', 'checked_out_at' => null]);
        $booking->room->update(['status' => 'occupied']);
        foreach (['available', 'maintenance', 'dirty', 'reserved'] as $status) {
            Room::query()->forceCreate([
                'name' => $status, 'room_category_id' => $booking->room->room_category_id,
                'price' => 100, 'status' => $status, 'created_at' => '2026-10-01 00:00:00',
            ]);
        }

        $this->getJson(self::BASE.'daily-summary?date_from=2026-10-22&date_to=2026-10-23')->assertOk()
            ->assertJsonPath('data.0.total_rooms', 5)
            ->assertJsonPath('data.0.occupied_rooms', 1)
            ->assertJsonPath('data.0.available_rooms', 4)
            ->assertJsonPath('data.1.total_rooms', 5)
            ->assertJsonPath('data.1.occupied_rooms', 1)
            ->assertJsonPath('data.1.available_rooms', 4);
    }

    private function admin(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 12:00:00'));
        $this->actingAs(User::factory()->create(['role' => 'hotel_administrator']));
    }

    /** @param array<string, mixed> $attributes */
    private function booking(array $attributes = []): Booking
    {
        $roomId = $attributes['room_id'] ?? Room::query()->forceCreate([
            'name' => fake()->unique()->numerify('Room ####'), 'room_category_id' => RoomCategory::query()->create(['name' => 'Deluxe'])->id,
            'price' => 100, 'status' => 'available', 'created_at' => '2026-10-01 00:00:00',
        ])->id;

        return Booking::query()->forceCreate(array_replace([
            'booking_no' => fake()->unique()->bothify('BK-########'), 'room_id' => $roomId, 'guest_name' => 'Mg Mg',
            'expected_check_in_at' => '2026-10-20 12:00:00', 'expected_check_out_at' => '2026-10-22 12:00:00',
            'checked_in_at' => '2026-10-20 12:00:00', 'checked_out_at' => '2026-10-22 12:00:00', 'status' => 'checked_out',
            'subtotal' => 100, 'total_amount' => 100, 'created_at' => '2026-10-20 12:00:00',
        ], $attributes));
    }

    private function payment(Booking $booking, string $type, float $amount, string $date = '2026-10-22 12:00:00'): void
    {
        $booking->payments()->create(['cashbook_id' => Cashbook::query()->firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'type' => 'cash'])->id, 'payment_no' => fake()->unique()->bothify('PAY-########'), 'payment_type' => $type, 'payment_method' => 'cash', 'amount' => $amount, 'paid_at' => $date]);
    }

    /** @param array<string, mixed> $attributes */
    private function order(Booking $booking, array $attributes = []): FoodOrder
    {
        return FoodOrder::query()->forceCreate(array_replace(['booking_id' => $booking->id, 'room_id' => $booking->room_id, 'order_no' => fake()->unique()->bothify('FO-########'), 'status' => 'served', 'created_at' => '2026-10-22 12:00:00'], $attributes));
    }

    private function menu(string $name, string $category): Menu
    {
        return Menu::query()->create(['name' => $name, 'code' => fake()->unique()->bothify('M-########'), 'price' => 15000,
            'menu_category_id' => MenuCategory::query()->create(['name' => $category, 'slug' => strtolower($category), 'code' => $category])->id,
            'warehouse_id' => Warehouse::query()->create(['name' => 'Kitchen', 'code' => fake()->unique()->bothify('W-########')])->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function item(array $attributes = []): Item
    {
        $group = UnitGroup::query()->firstOrCreate(['slug' => 'quantity'], ['name' => 'Quantity']);
        $unit = Unit::query()->firstOrCreate(['symbol' => 'pc'], ['name' => 'Piece', 'unit_group_id' => $group->id]);
        $category = ItemCategory::query()->firstOrCreate(['slug' => 'supplies'], ['name' => 'Supplies', 'code' => 'SUPPLIES']);

        return Item::query()->create(array_replace(['name' => 'Rice', 'code' => fake()->unique()->bothify('I-########'), 'stock_unit_id' => $unit->id, 'item_category_id' => $category->id], $attributes));
    }

    private function ledger(Item $item, Warehouse $warehouse, string $type, float $quantity, string $date = '2026-10-22 12:00:00'): void
    {
        InventoryLedger::query()->create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'unit_id' => $item->stock_unit_id,
            'transaction_type' => $type, 'quantity' => abs($quantity), 'base_quantity' => $quantity, 'balance_quantity' => 0, 'transaction_date' => $date]);
    }
}
