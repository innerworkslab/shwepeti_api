<?php

namespace Tests\Feature;

use App\Enums\FoodOrderStatusEnum;
use App\Enums\ServiceOrderStatusEnum;
use App\Enums\ServiceOrderTypeEnum;
use App\Enums\UserPortalAccessEnum;
use App\Enums\UserRoleEnum;
use App\Models\InventoryLedger;
use App\Models\InventoryStockBalance;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PosOrderModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_food_order_includes_frontend_calculated_charges(): void
    {
        $menu = $this->createMenu(['price' => 150000]);
        $headers = $this->posAuthHeaders();
        [$bookingId] = $this->createCheckedInBooking($headers);

        $response = $this->withHeaders($headers)->postJson('/api/v1/pos/food-orders', [
            'booking_id' => $bookingId,
            'items' => [['menu_id' => $menu->id, 'qty' => 1]],
            'tax_percentage' => 10,
            'tax_amount' => 15000,
            'service_charge_percentage' => 5,
            'service_charge_amount' => 7500,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.tax_percentage', '10.00')
            ->assertJsonPath('data.tax_amount', '15000.00')
            ->assertJsonPath('data.service_charge_percentage', '5.00')
            ->assertJsonPath('data.service_charge_amount', '7500.00')
            ->assertJsonPath('data.total_amount', '172500.00');
        $this->assertDatabaseHas('food_orders', [
            'id' => $response->json('data.id'),
            'tax_percentage' => 10,
            'tax_amount' => 15000,
            'service_charge_percentage' => 5,
            'service_charge_amount' => 7500,
            'total_amount' => 172500,
        ]);
    }

    #[TestWith([['tax_percentage' => 101, 'tax_amount' => 0], 'tax_percentage'])]
    #[TestWith([['service_charge_percentage' => -1, 'service_charge_amount' => 0], 'service_charge_percentage'])]
    #[TestWith([['tax_percentage' => 10], 'tax_amount'])]
    #[TestWith([['service_charge_percentage' => 5], 'service_charge_amount'])]
    #[TestWith([['service_charge_amount' => -1], 'service_charge_amount'])]
    public function test_food_order_rejects_invalid_charges(array $charges, string $errorField): void
    {
        $menu = $this->createMenu();
        $headers = $this->posAuthHeaders();
        [$bookingId] = $this->createCheckedInBooking($headers);

        $this->withHeaders($headers)->postJson('/api/v1/pos/food-orders', [
            'booking_id' => $bookingId,
            'items' => [['menu_id' => $menu->id, 'qty' => 1]],
            ...$charges,
        ])->assertUnprocessable()->assertJsonValidationErrors($errorField);

        $this->assertDatabaseMissing('food_orders', ['booking_id' => $bookingId]);
    }

    public function test_it_creates_food_order_from_menus(): void
    {
        $menu = $this->createMenu(['price' => 5000]);
        $headers = $this->posAuthHeaders();
        [$bookingId] = $this->createCheckedInBooking($headers);

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/pos/food-orders', [
                'booking_id' => $bookingId,
                'items' => [
                    [
                        'menu_id' => $menu->id,
                        'qty' => 2,
                        'note' => 'Less spicy',
                    ],
                ],
                'discount_amount' => 500,
                'tax_amount' => 250,
                'note' => 'Send to room',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', FoodOrderStatusEnum::Pending->value)
            ->assertJsonPath('data.subtotal', '10000.00')
            ->assertJsonPath('data.discount_amount', '500.00')
            ->assertJsonPath('data.tax_amount', '250.00')
            ->assertJsonPath('data.total_amount', '9750.00')
            ->assertJsonPath('data.items.0.menu_id', $menu->id)
            ->assertJsonPath('data.items.0.unit_price', '5000.00')
            ->assertJsonPath('data.items.0.total', '10000.00');
    }

    public function test_it_updates_food_order_status(): void
    {
        $menu = $this->createMenu(['price' => 5000]);
        $headers = $this->posAuthHeaders();
        [$bookingId] = $this->createCheckedInBooking($headers);

        $foodOrderId = $this->withHeaders($headers)
            ->postJson('/api/v1/pos/food-orders', [
                'booking_id' => $bookingId,
                'items' => [
                    ['menu_id' => $menu->id, 'qty' => 1],
                ],
            ])->json('data.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/pos/food-orders/{$foodOrderId}/status", [
                'status' => FoodOrderStatusEnum::Preparing->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', FoodOrderStatusEnum::Preparing->value);
    }

    public function test_food_order_consumes_menu_recipe_inventory_from_menu_warehouse(): void
    {
        $warehouse = Warehouse::query()->create(['code' => 'KITCHEN', 'name' => 'Kitchen Store']);
        $unitGroup = UnitGroup::query()->create(['name' => 'Weight', 'slug' => 'weight']);
        $kg = Unit::query()->create(['unit_group_id' => $unitGroup->id, 'name' => 'kg', 'symbol' => 'kg', 'is_base' => true]);
        $category = ItemCategory::query()->create(['name' => 'Raw Materials', 'slug' => 'raw-materials', 'code' => 'RAW_MATERIALS']);
        $item = Item::query()->create([
            'item_category_id' => $category->id,
            'name' => 'Chicken',
            'code' => 'CHICKEN',
            'stock_unit_id' => $kg->id,
            'is_active' => true,
        ]);
        $menu = $this->createMenu([
            'warehouse_id' => $warehouse->id,
            'price' => 5000,
        ]);
        $menu->recipes()->create([
            'item_id' => $item->id,
            'unit_id' => $kg->id,
            'quantity' => 1,
            'base_quantity' => 1,
        ]);

        InventoryStockBalance::query()->create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'reserved_quantity' => 0,
            'available_quantity' => 5,
        ]);

        $headers = $this->posAuthHeaders();
        [$bookingId] = $this->createCheckedInBooking($headers);

        $foodOrderId = $this->withHeaders($headers)
            ->postJson('/api/v1/pos/food-orders', [
                'booking_id' => $bookingId,
                'items' => [
                    ['menu_id' => $menu->id, 'qty' => 2],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(0, InventoryLedger::query()->where('transaction_type', 'food_order')->count());

        $this->withHeaders($headers)
            ->postJson("/api/v1/pos/food-orders/{$foodOrderId}/status", [
                'status' => FoodOrderStatusEnum::Preparing->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', FoodOrderStatusEnum::Preparing->value);

        $this->assertDatabaseHas('inventory_stock_balances', [
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 3,
            'available_quantity' => 3,
        ]);
        $this->assertDatabaseHas('inventory_ledgers', [
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'transaction_type' => 'food_order',
            'reference_type' => 'food_order_item',
            'base_quantity' => -2,
            'balance_quantity' => 3,
        ]);
        $this->assertSame(1, InventoryLedger::query()->where('transaction_type', 'food_order')->count());
    }

    public function test_it_blocks_food_order_cancel_after_preparing(): void
    {
        $menu = $this->createMenu(['price' => 5000]);
        $headers = $this->posAuthHeaders();
        [$bookingId] = $this->createCheckedInBooking($headers);

        $foodOrderId = $this->withHeaders($headers)
            ->postJson('/api/v1/pos/food-orders', [
                'booking_id' => $bookingId,
                'items' => [
                    ['menu_id' => $menu->id, 'qty' => 1],
                ],
            ])->json('data.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/pos/food-orders/{$foodOrderId}/status", [
                'status' => FoodOrderStatusEnum::Preparing->value,
            ])->assertOk();

        $this->withHeaders($headers)
            ->postJson("/api/v1/pos/food-orders/{$foodOrderId}/status", [
                'status' => FoodOrderStatusEnum::Cancelled->value,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_it_creates_service_order_from_amenity_items(): void
    {
        $item = $this->createServiceItem(ServiceOrderTypeEnum::Amenity->value, ['price' => 3000]);
        $headers = $this->posAuthHeaders();
        [$bookingId] = $this->createCheckedInBooking($headers);

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/pos/service-orders', [
                'booking_id' => $bookingId,
                'type' => ServiceOrderTypeEnum::Amenity->value,
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 3,
                        'note' => 'Extra towel',
                    ],
                ],
                'note' => 'Guest request',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', ServiceOrderTypeEnum::Amenity->value)
            ->assertJsonPath('data.status', ServiceOrderStatusEnum::Pending->value)
            ->assertJsonPath('data.subtotal', '9000.00')
            ->assertJsonPath('data.total_amount', '9000.00')
            ->assertJsonPath('data.items.0.item_id', $item->id)
            ->assertJsonPath('data.items.0.unit_price', '3000.00')
            ->assertJsonPath('data.items.0.total', '9000.00');
    }

    public function test_it_blocks_service_order_items_that_do_not_match_type(): void
    {
        $item = $this->createServiceItem(ServiceOrderTypeEnum::Laundry->value, ['price' => 3000]);
        $headers = $this->posAuthHeaders();
        [$bookingId] = $this->createCheckedInBooking($headers);

        $this->withHeaders($headers)
            ->postJson('/api/v1/pos/service-orders', [
                'booking_id' => $bookingId,
                'type' => ServiceOrderTypeEnum::Amenity->value,
                'items' => [
                    ['item_id' => $item->id, 'qty' => 1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_it_lists_pos_menus_and_service_items(): void
    {
        $menu = $this->createMenu(['price' => 5000]);
        $item = $this->createServiceItem(ServiceOrderTypeEnum::Laundry->value, ['price' => 3000]);
        $headers = $this->posAuthHeaders();

        $this->withHeaders($headers)
            ->getJson('/api/v1/pos/menus')
            ->assertOk()
            ->assertJsonPath('data.0.id', $menu->id);

        $this->withHeaders($headers)
            ->getJson('/api/v1/pos/items?category=laundry')
            ->assertOk()
            ->assertJsonPath('data.0.id', $item->id)
            ->assertJsonPath('data.0.units.*.id', [$item->stock_unit_id]);
    }

    public function test_booking_show_includes_food_and_service_orders_with_effective_amounts(): void
    {
        $room = $this->createAvailableRoom(['price' => 100]);
        $menu = $this->createMenu(['price' => 20]);
        $item = $this->createServiceItem(ServiceOrderTypeEnum::Amenity->value, ['price' => 15]);
        $headers = $this->posAuthHeaders();

        $bookingId = $this->withHeaders($headers)
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'guest_name' => 'Order Guest',
                'expected_check_in_at' => now()->toDateTimeString(),
                'expected_check_out_at' => now()->addDay()->toDateTimeString(),
                'check_in_now' => true,
            ])->json('data.id');

        $this->withHeaders($headers)
            ->postJson('/api/v1/pos/food-orders', [
                'booking_id' => $bookingId,
                'items' => [
                    ['menu_id' => $menu->id, 'qty' => 2],
                ],
            ])->assertCreated();

        $this->withHeaders($headers)
            ->postJson('/api/v1/pos/service-orders', [
                'booking_id' => $bookingId,
                'type' => ServiceOrderTypeEnum::Amenity->value,
                'items' => [
                    ['item_id' => $item->id, 'qty' => 2],
                ],
            ])->assertCreated();

        $this->withHeaders($headers)
            ->getJson("/api/v1/pos/bookings/{$bookingId}")
            ->assertOk()
            ->assertJsonPath('data.room_total_amount', '100.00')
            ->assertJsonPath('data.food_order_amount', '40.00')
            ->assertJsonPath('data.service_order_amount', '30.00')
            ->assertJsonPath('data.order_amount', '70.00')
            ->assertJsonPath('data.grand_total_amount', '170.00')
            ->assertJsonPath('data.balance_amount', '170.00')
            ->assertJsonPath('data.food_orders.0.total_amount', '40.00')
            ->assertJsonPath('data.service_orders.0.total_amount', '30.00');

        $this->withHeaders($headers)
            ->getJson("/api/v1/pos/rooms/{$room->id}")
            ->assertOk()
            ->assertJsonPath('data.current_booking.id', $bookingId)
            ->assertJsonPath('data.current_booking.food_order_amount', '40.00')
            ->assertJsonPath('data.current_booking.service_order_amount', '30.00')
            ->assertJsonPath('data.current_booking.order_amount', '70.00')
            ->assertJsonPath('data.current_booking.grand_total_amount', '170.00')
            ->assertJsonPath('data.current_booking.balance_amount', '170.00')
            ->assertJsonPath('data.bookings.0.food_order_amount', '40.00')
            ->assertJsonPath('data.bookings.0.service_order_amount', '30.00');
    }

    public function test_it_blocks_orders_for_reserved_booking(): void
    {
        $room = $this->createAvailableRoom();
        $menu = $this->createMenu(['price' => 20]);
        $headers = $this->posAuthHeaders();

        $bookingId = $this->withHeaders($headers)
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'guest_name' => 'Reserved Guest',
                'expected_check_in_at' => now()->toDateTimeString(),
                'expected_check_out_at' => now()->addDay()->toDateTimeString(),
            ])->json('data.id');

        $this->withHeaders($headers)
            ->postJson('/api/v1/pos/food-orders', [
                'booking_id' => $bookingId,
                'items' => [
                    ['menu_id' => $menu->id, 'qty' => 1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMenu(array $overrides = []): Menu
    {
        $category = MenuCategory::query()->create([
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(),
            'code' => fake()->unique()->bothify('MC-###'),
            'is_active' => true,
        ]);
        $warehouseId = $overrides['warehouse_id'] ?? Warehouse::query()->create([
            'code' => fake()->unique()->bothify('WH-###'),
            'name' => fake()->unique()->words(2, true),
        ])->id;

        return Menu::query()->create(array_merge([
            'menu_category_id' => $category->id,
            'warehouse_id' => $warehouseId,
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->bothify('MENU-###'),
            'price' => 5000,
            'is_available' => true,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createAvailableRoom(array $overrides = []): Room
    {
        $category = RoomCategory::query()->create([
            'name' => fake()->unique()->word(),
            'is_active' => true,
        ]);

        return Room::query()->create(array_merge([
            'name' => fake()->unique()->numerify('Room ###'),
            'room_category_id' => $category->id,
            'price' => 100,
            'status' => 'available',
        ], $overrides));
    }

    /**
     * @return array{0: int, 1: Room}
     */
    private function createCheckedInBooking(array $headers): array
    {
        $room = $this->createAvailableRoom();

        $bookingId = $this->withHeaders($headers)
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'guest_name' => fake()->name(),
                'expected_check_in_at' => now()->toDateTimeString(),
                'expected_check_out_at' => now()->addDay()->toDateTimeString(),
                'check_in_now' => true,
            ])->json('data.id');

        return [$bookingId, $room];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createServiceItem(string $type, array $overrides = []): Item
    {
        $categoryName = $type === ServiceOrderTypeEnum::Amenity->value ? 'Amenities' : 'Laundry';
        $category = ItemCategory::query()->create([
            'name' => $categoryName,
            'slug' => Str::slug($categoryName).'-'.fake()->unique()->randomNumber(4),
            'code' => Str::of($categoryName)->upper()->toString(),
            'is_active' => true,
        ]);

        $unitGroup = UnitGroup::query()->create([
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(),
            'is_active' => true,
        ]);

        $unit = Unit::query()->create([
            'unit_group_id' => $unitGroup->id,
            'name' => 'pcs',
            'symbol' => 'pcs',
            'is_base' => true,
            'is_active' => true,
        ]);

        return Item::query()->create(array_merge([
            'item_category_id' => $category->id,
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->bothify('ITEM-###'),
            'stock_unit_id' => $unit->id,
            'price' => 3000,
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
