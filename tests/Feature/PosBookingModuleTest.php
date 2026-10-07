<?php

namespace Tests\Feature;

use App\Enums\BookingChargeTypeEnum;
use App\Enums\BookingPaymentTypeEnum;
use App\Enums\BookingStatusEnum;
use App\Enums\BookingTypeEnum;
use App\Enums\CashbookTypeEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\RoomStatusEnum;
use App\Enums\UserPortalAccessEnum;
use App\Enums\UserRoleEnum;
use App\Models\Cashbook;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosBookingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_rooms_for_pos_portal(): void
    {
        $category = RoomCategory::query()->create([
            'name' => 'Standard',
            'is_active' => true,
        ]);

        Room::query()->create([
            'name' => 'Room 101',
            'room_category_id' => $category->id,
            'price' => 100,
            'status' => RoomStatusEnum::Available->value,
        ]);

        $this->withHeaders($this->posAuthHeaders())
            ->getJson('/api/v1/pos/rooms')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Room 101')
            ->assertJsonPath('data.0.status', RoomStatusEnum::Available->value);
    }

    public function test_it_creates_a_reserved_booking_and_marks_room_reserved(): void
    {
        $room = $this->createAvailableRoom();

        $response = $this->withHeaders($this->posAuthHeaders())
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'booking_type' => BookingTypeEnum::Reservation->value,
                'guest_name' => ' Mg Mg ',
                'guest_phone' => '09123456789',
                'expected_check_in_at' => now()->addHour()->toDateTimeString(),
                'expected_check_out_at' => now()->addDay()->toDateTimeString(),
                'guest_count' => 2,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.guest_name', 'Mg Mg')
            ->assertJsonPath('data.booking_type', BookingTypeEnum::Reservation->value)
            ->assertJsonPath('data.charge_type', BookingChargeTypeEnum::Day->value)
            ->assertJsonPath('data.status', BookingStatusEnum::Reserved->value)
            ->assertJsonPath('data.room.status', RoomStatusEnum::Reserved->value);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'status' => RoomStatusEnum::Reserved->value,
        ]);
    }

    public function test_it_creates_a_reservation_booking_with_deposit_payment(): void
    {
        $room = $this->createAvailableRoom([
            'price' => 100,
        ]);
        $cashbook = $this->createCashbook();

        $response = $this->withHeaders($this->posAuthHeaders())
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'booking_type' => BookingTypeEnum::Reservation->value,
                'guest_name' => 'Deposit Guest',
                'expected_check_in_at' => now()->addHour()->toDateTimeString(),
                'expected_check_out_at' => now()->addDay()->toDateTimeString(),
                'deposit' => [
                    'cashbook_id' => $cashbook->id,
                    'amount' => 40,
                    'payment_method' => PaymentMethodEnum::Cash->value,
                    'note' => 'Reservation deposit',
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.booking_type', BookingTypeEnum::Reservation->value)
            ->assertJsonPath('data.status', BookingStatusEnum::Reserved->value)
            ->assertJsonPath('data.total_amount', '100.00')
            ->assertJsonPath('data.paid_amount', '40.00')
            ->assertJsonPath('data.balance_amount', '60.00')
            ->assertJsonPath('data.payments.0.payment_type', BookingPaymentTypeEnum::Deposit->value)
            ->assertJsonPath('data.payments.0.cashbook_id', $cashbook->id)
            ->assertJsonPath('data.payments.0.payment_method', PaymentMethodEnum::Cash->value)
            ->assertJsonPath('data.payments.0.amount', '40.00');

        $this->assertDatabaseHas('booking_payments', [
            'booking_id' => $response->json('data.id'),
            'cashbook_id' => $cashbook->id,
            'payment_type' => BookingPaymentTypeEnum::Deposit->value,
            'payment_method' => PaymentMethodEnum::Cash->value,
            'amount' => 40,
        ]);
    }

    public function test_it_creates_a_session_booking_using_session_rate(): void
    {
        $room = $this->createAvailableRoom([
            'price' => 100,
        ]);

        $this->withHeaders($this->posAuthHeaders())
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'booking_type' => BookingTypeEnum::WalkIn->value,
                'charge_type' => BookingChargeTypeEnum::Session->value,
                'guest_name' => 'Session Guest',
                'expected_check_in_at' => now()->toDateTimeString(),
                'expected_check_out_at' => now()->addHours(3)->toDateTimeString(),
                'session_hours' => 3,
                'session_rate' => 25000,
                'check_in_now' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.booking_type', BookingTypeEnum::WalkIn->value)
            ->assertJsonPath('data.charge_type', BookingChargeTypeEnum::Session->value)
            ->assertJsonPath('data.session_hours', 3)
            ->assertJsonPath('data.session_rate', '25000.00')
            ->assertJsonPath('data.subtotal', '25000.00')
            ->assertJsonPath('data.total_amount', '25000.00');
    }

    public function test_it_requires_session_fields_for_session_booking(): void
    {
        $room = $this->createAvailableRoom();

        $this->withHeaders($this->posAuthHeaders())
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'booking_type' => BookingTypeEnum::WalkIn->value,
                'charge_type' => BookingChargeTypeEnum::Session->value,
                'guest_name' => 'Session Guest',
                'expected_check_in_at' => now()->toDateTimeString(),
                'expected_check_out_at' => now()->addHours(3)->toDateTimeString(),
                'check_in_now' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_it_blocks_deposit_payment_for_walk_in_booking(): void
    {
        $room = $this->createAvailableRoom();
        $cashbook = $this->createCashbook();

        $this->withHeaders($this->posAuthHeaders())
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'booking_type' => BookingTypeEnum::WalkIn->value,
                'guest_name' => 'Walk In Guest',
                'expected_check_in_at' => now()->toDateTimeString(),
                'expected_check_out_at' => now()->addDay()->toDateTimeString(),
                'check_in_now' => true,
                'deposit' => [
                    'cashbook_id' => $cashbook->id,
                    'amount' => 40,
                    'payment_method' => PaymentMethodEnum::Cash->value,
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_it_checks_in_and_checks_out_a_booking(): void
    {
        $room = $this->createAvailableRoom();
        $headers = $this->posAuthHeaders();

        $bookingId = $this->withHeaders($headers)
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'guest_name' => 'Aung Aung',
                'expected_check_in_at' => now()->toDateTimeString(),
                'expected_check_out_at' => now()->addDay()->toDateTimeString(),
            ])->json('data.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/pos/bookings/{$bookingId}/check-in")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatusEnum::CheckedIn->value)
            ->assertJsonPath('data.room.status', RoomStatusEnum::Occupied->value);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'status' => RoomStatusEnum::Occupied->value,
        ]);

        $this->withHeaders($headers)
            ->postJson("/api/v1/pos/bookings/{$bookingId}/check-out")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatusEnum::CheckedOut->value)
            ->assertJsonPath('data.room.status', RoomStatusEnum::Dirty->value);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'status' => RoomStatusEnum::Dirty->value,
        ]);
    }

    public function test_it_checks_out_with_checkout_payment(): void
    {
        $room = $this->createAvailableRoom([
            'price' => 100,
        ]);
        $headers = $this->posAuthHeaders();
        $cashbook = $this->createCashbook();

        $bookingId = $this->withHeaders($headers)
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'guest_name' => 'Checkout Guest',
                'expected_check_in_at' => now()->toDateTimeString(),
                'expected_check_out_at' => now()->addDay()->toDateTimeString(),
                'check_in_now' => true,
            ])->json('data.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/pos/bookings/{$bookingId}/checkout", [
                'cashbook_id' => $cashbook->id,
                'payment_method' => PaymentMethodEnum::Cash->value,
                'amount' => 100,
                'note' => 'Final payment',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatusEnum::CheckedOut->value)
            ->assertJsonPath('data.paid_amount', '100.00')
            ->assertJsonPath('data.balance_amount', '0.00')
            ->assertJsonPath('data.room.status', RoomStatusEnum::Dirty->value)
            ->assertJsonPath('data.payments.0.payment_type', BookingPaymentTypeEnum::Checkout->value)
            ->assertJsonPath('data.payments.0.cashbook_id', $cashbook->id)
            ->assertJsonPath('data.payments.0.payment_method', PaymentMethodEnum::Cash->value);

        $this->assertDatabaseHas('booking_payments', [
            'booking_id' => $bookingId,
            'cashbook_id' => $cashbook->id,
            'payment_type' => BookingPaymentTypeEnum::Checkout->value,
            'payment_method' => PaymentMethodEnum::Cash->value,
            'amount' => 100,
        ]);
    }

    public function test_it_adds_partial_payment_to_checked_in_booking(): void
    {
        $room = $this->createAvailableRoom([
            'price' => 100,
        ]);
        $headers = $this->posAuthHeaders();
        $cashbook = $this->createCashbook();

        $bookingId = $this->withHeaders($headers)
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'guest_name' => 'Partial Guest',
                'expected_check_in_at' => now()->toDateTimeString(),
                'expected_check_out_at' => now()->addDay()->toDateTimeString(),
                'check_in_now' => true,
            ])->json('data.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/pos/bookings/{$bookingId}/payments", [
                'cashbook_id' => $cashbook->id,
                'payment_method' => PaymentMethodEnum::KPay->value,
                'amount' => 40,
                'note' => 'Partial payment',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatusEnum::CheckedIn->value)
            ->assertJsonPath('data.paid_amount', '40.00')
            ->assertJsonPath('data.balance_amount', '60.00')
            ->assertJsonPath('data.payments.0.payment_type', BookingPaymentTypeEnum::Partial->value)
            ->assertJsonPath('data.payments.0.cashbook_id', $cashbook->id)
            ->assertJsonPath('data.payments.0.payment_method', PaymentMethodEnum::KPay->value);

        $this->assertDatabaseHas('booking_payments', [
            'booking_id' => $bookingId,
            'cashbook_id' => $cashbook->id,
            'payment_type' => BookingPaymentTypeEnum::Partial->value,
            'payment_method' => PaymentMethodEnum::KPay->value,
            'amount' => 40,
        ]);
    }

    public function test_it_blocks_partial_payment_above_remaining_balance(): void
    {
        $room = $this->createAvailableRoom([
            'price' => 100,
        ]);
        $headers = $this->posAuthHeaders();
        $cashbook = $this->createCashbook();

        $bookingId = $this->withHeaders($headers)
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'guest_name' => 'Partial Guest',
                'expected_check_in_at' => now()->toDateTimeString(),
                'expected_check_out_at' => now()->addDay()->toDateTimeString(),
                'check_in_now' => true,
            ])->json('data.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/pos/bookings/{$bookingId}/payments", [
                'cashbook_id' => $cashbook->id,
                'payment_method' => PaymentMethodEnum::Cash->value,
                'amount' => 120,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_it_blocks_checkout_payment_below_remaining_balance(): void
    {
        $room = $this->createAvailableRoom([
            'price' => 100,
        ]);
        $headers = $this->posAuthHeaders();
        $cashbook = $this->createCashbook();

        $bookingId = $this->withHeaders($headers)
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'guest_name' => 'Checkout Guest',
                'expected_check_in_at' => now()->toDateTimeString(),
                'expected_check_out_at' => now()->addDay()->toDateTimeString(),
                'check_in_now' => true,
            ])->json('data.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/pos/bookings/{$bookingId}/checkout", [
                'cashbook_id' => $cashbook->id,
                'payment_method' => PaymentMethodEnum::Cash->value,
                'amount' => 50,
                'note' => 'Final payment',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_it_blocks_booking_a_room_that_is_not_available(): void
    {
        $room = $this->createAvailableRoom([
            'status' => RoomStatusEnum::Occupied->value,
        ]);

        $this->withHeaders($this->posAuthHeaders())
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'guest_name' => 'Aung Aung',
                'expected_check_in_at' => now()->toDateTimeString(),
                'expected_check_out_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_it_defaults_immediate_check_in_to_walk_in_booking_type(): void
    {
        $room = $this->createAvailableRoom();

        $this->withHeaders($this->posAuthHeaders())
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'guest_name' => 'Walk In Guest',
                'expected_check_in_at' => now()->toDateTimeString(),
                'expected_check_out_at' => now()->addDay()->toDateTimeString(),
                'check_in_now' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.booking_type', BookingTypeEnum::WalkIn->value)
            ->assertJsonPath('data.status', BookingStatusEnum::CheckedIn->value);
    }

    public function test_it_checks_in_walk_in_booking_even_without_check_in_now_flag(): void
    {
        $room = $this->createAvailableRoom();

        $this->withHeaders($this->posAuthHeaders())
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'booking_type' => BookingTypeEnum::WalkIn->value,
                'guest_name' => 'Walk In Guest',
                'expected_check_in_at' => now()->toDateTimeString(),
                'expected_check_out_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.booking_type', BookingTypeEnum::WalkIn->value)
            ->assertJsonPath('data.status', BookingStatusEnum::CheckedIn->value)
            ->assertJsonPath('data.room.status', RoomStatusEnum::Occupied->value);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'status' => RoomStatusEnum::Occupied->value,
        ]);
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
            'status' => RoomStatusEnum::Available->value,
        ], $overrides));
    }

    private function createCashbook(): Cashbook
    {
        return Cashbook::query()->create([
            'code' => fake()->unique()->bothify('CB-###'),
            'name' => fake()->unique()->words(2, true),
            'type' => CashbookTypeEnum::Cash->value,
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);
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
