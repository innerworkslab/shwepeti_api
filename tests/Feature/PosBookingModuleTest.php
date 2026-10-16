<?php

namespace Tests\Feature;

use App\Enums\BookingChargeTypeEnum;
use App\Enums\BookingPaymentTypeEnum;
use App\Enums\BookingStatusEnum;
use App\Enums\BookingTypeEnum;
use App\Enums\CashbookTransactionTypeEnum;
use App\Enums\CashbookTypeEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\RoomStatusEnum;
use App\Enums\UserPortalAccessEnum;
use App\Enums\UserRoleEnum;
use App\Models\Booking;
use App\Models\Cashbook;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
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

    public function test_it_lists_pos_rooms_with_bookings_filtered_by_date(): void
    {
        $room = $this->createAvailableRoom([
            'name' => 'Room 201',
            'status' => RoomStatusEnum::Reserved->value,
        ]);

        $matchingBooking = Booking::query()->create([
            'booking_no' => 'BK-DATE-001',
            'room_id' => $room->id,
            'booking_type' => BookingTypeEnum::Reservation->value,
            'charge_type' => BookingChargeTypeEnum::Day->value,
            'guest_name' => 'Date Filter Guest',
            'expected_check_in_at' => '2026-10-14 12:30:00',
            'expected_check_out_at' => '2026-10-15 11:30:00',
            'status' => BookingStatusEnum::Reserved->value,
            'room_rate' => 100,
            'subtotal' => 100,
            'total_amount' => 100,
            'paid_amount' => 0,
            'balance_amount' => 100,
        ]);

        Booking::query()->create([
            'booking_no' => 'BK-DATE-002',
            'room_id' => $room->id,
            'booking_type' => BookingTypeEnum::Reservation->value,
            'charge_type' => BookingChargeTypeEnum::Day->value,
            'guest_name' => 'Other Date Guest',
            'expected_check_in_at' => '2026-10-16 12:30:00',
            'expected_check_out_at' => '2026-10-17 11:30:00',
            'status' => BookingStatusEnum::Reserved->value,
            'room_rate' => 100,
            'subtotal' => 100,
            'total_amount' => 100,
            'paid_amount' => 0,
            'balance_amount' => 100,
        ]);

        $this->withHeaders($this->posAuthHeaders())
            ->getJson('/api/v1/pos/rooms?date=2026-10-14')
            ->assertOk()
            ->assertJsonPath('data.0.id', $room->id)
            ->assertJsonPath('data.0.current_booking.id', $matchingBooking->id)
            ->assertJsonCount(1, 'data.0.bookings')
            ->assertJsonPath('data.0.bookings.0.id', $matchingBooking->id)
            ->assertJsonPath('data.0.bookings.0.guest_name', 'Date Filter Guest')
            ->assertJsonMissing(['guest_name' => 'Other Date Guest']);
    }

    #[TestWith(['reserved', 'confirmed', '#3b82f6'])]
    #[TestWith(['checked_in', 'checked-in', '#10b981'])]
    public function test_it_returns_booking_dashboard_grouped_by_room_category(string $status, string $expectedStatus, string $expectedColor): void
    {
        $category = RoomCategory::query()->create([
            'name' => 'Standard',
            'is_active' => true,
        ]);

        $roomOne = Room::query()->create([
            'name' => 'Room 101',
            'room_category_id' => $category->id,
            'price' => 100,
            'status' => RoomStatusEnum::Available->value,
        ]);

        Room::query()->create([
            'name' => 'Room 102',
            'room_category_id' => $category->id,
            'price' => 100,
            'status' => RoomStatusEnum::Available->value,
        ]);

        $response = $this->withHeaders($this->posAuthHeaders())
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $roomOne->id,
                'booking_type' => BookingTypeEnum::Reservation->value,
                'guest_name' => 'Dashboard Guest',
                'guest_count' => 2,
                'expected_check_in_at' => '2026-10-07 14:00:00',
                'expected_check_out_at' => '2026-10-08 12:00:00',
            ])->assertCreated();

        Booking::query()->findOrFail($response->json('data.id'))->update(['status' => $status]);

        RoomCategory::query()->create([
            'name' => 'Suite',
            'is_active' => true,
        ]);

        $this->withHeaders($this->posAuthHeaders())
            ->getJson('/api/v1/pos/booking-dashboard?start_date=2026-10-07&end_date=2026-10-08')
            ->assertOk()
            ->assertJsonPath('data.start_date', '2026-10-07')
            ->assertJsonPath('data.end_date', '2026-10-08')
            ->assertJsonPath('data.dates.0.date', '2026-10-07')
            ->assertJsonPath('data.room_categories.0.name', 'Standard')
            ->assertJsonPath('data.room_categories.0.availability.0.total_count', 2)
            ->assertJsonPath('data.room_categories.0.availability.0.booked_count', 1)
            ->assertJsonPath('data.room_categories.0.availability.0.available_count', 1)
            ->assertJsonCount(2, 'data.room_categories.0.rooms')
            ->assertJsonMissingPath('data.room_categories.0.rooms.0.bookings')
            ->assertJsonMissingPath('data.room_categories.0.rooms.1.bookings')
            ->assertJsonPath('data.room_categories.0.bookings', [[
                'roomId' => (string) $roomOne->id,
                'guestName' => 'Dashboard Guest',
                'start_date_time' => '2026-10-07 12:30',
                'end_date_time' => '2026-10-08 11:30',
                'status' => $expectedStatus,
                'totalGuests' => 2,
                'colorCode' => $expectedColor,
            ]])
            ->assertJsonPath('data.room_categories.1.bookings', []);
    }

    public function test_it_accepts_per_page_as_camel_case_for_pos_booking_lists(): void
    {
        $room = $this->createAvailableRoom();

        Booking::query()->create([
            'booking_no' => 'BK-TEST-001',
            'room_id' => $room->id,
            'booking_type' => BookingTypeEnum::Reservation->value,
            'charge_type' => BookingChargeTypeEnum::Day->value,
            'guest_name' => 'First Guest',
            'expected_check_in_at' => now()->addHour(),
            'expected_check_out_at' => now()->addDay(),
            'status' => BookingStatusEnum::Reserved->value,
            'room_rate' => 100,
            'subtotal' => 100,
            'total_amount' => 100,
            'paid_amount' => 0,
            'balance_amount' => 100,
        ]);

        Booking::query()->create([
            'booking_no' => 'BK-TEST-002',
            'room_id' => $room->id,
            'booking_type' => BookingTypeEnum::Reservation->value,
            'charge_type' => BookingChargeTypeEnum::Day->value,
            'guest_name' => 'Second Guest',
            'expected_check_in_at' => now()->addHours(2),
            'expected_check_out_at' => now()->addDays(2),
            'status' => BookingStatusEnum::Reserved->value,
            'room_rate' => 100,
            'subtotal' => 100,
            'total_amount' => 100,
            'paid_amount' => 0,
            'balance_amount' => 100,
        ]);

        $this->withHeaders($this->posAuthHeaders())
            ->getJson('/api/v1/pos/bookings?page=1&perPage=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1);
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

        $this->assertDatabaseHas('cashbook_transactions', [
            'cashbook_id' => $cashbook->id,
            'transaction_type' => CashbookTransactionTypeEnum::Income->value,
            'amount' => 40,
            'balance_after' => 40,
            'reference_type' => 'booking_payment',
        ]);
        $this->assertDatabaseHas('cashbooks', [
            'id' => $cashbook->id,
            'current_balance' => 40,
        ]);
    }

    public function test_it_normalizes_reservation_times_and_blocks_overlapping_room_booking(): void
    {
        $room = $this->createAvailableRoom();
        $headers = $this->posAuthHeaders();

        $bookingId = $this->withHeaders($headers)
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'booking_type' => BookingTypeEnum::Reservation->value,
                'guest_name' => 'Reservation Guest',
                'expected_check_in_at' => '2026-10-14 08:00:00',
                'expected_check_out_at' => '2026-10-15 20:00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.expected_check_in_at', '2026-10-14 12:30:00')
            ->assertJsonPath('data.expected_check_out_at', '2026-10-15 11:30:00')
            ->json('data.id');

        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'expected_check_in_at' => '2026-10-14 12:30:00',
            'expected_check_out_at' => '2026-10-15 11:30:00',
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'booking_type' => BookingTypeEnum::Reservation->value,
                'guest_name' => 'Overlapping Guest',
                'expected_check_in_at' => '2026-10-14',
                'expected_check_out_at' => '2026-10-16',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room_id');

        $this->withHeaders($headers)
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'booking_type' => BookingTypeEnum::Reservation->value,
                'guest_name' => 'Next Reservation Guest',
                'expected_check_in_at' => '2026-10-15',
                'expected_check_out_at' => '2026-10-16',
            ])
            ->assertCreated()
            ->assertJsonPath('data.expected_check_in_at', '2026-10-15 12:30:00')
            ->assertJsonPath('data.expected_check_out_at', '2026-10-16 11:30:00');
    }

    public function test_it_blocks_same_day_reservation_after_policy_time_normalization(): void
    {
        $room = $this->createAvailableRoom();

        $this->withHeaders($this->posAuthHeaders())
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'booking_type' => BookingTypeEnum::Reservation->value,
                'guest_name' => 'Invalid Reservation Guest',
                'expected_check_in_at' => '2026-10-14 08:00:00',
                'expected_check_out_at' => '2026-10-14 20:00:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expected_check_out_at');
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

    public function test_it_blocks_overlapping_walk_in_session_and_allows_back_to_back_session(): void
    {
        $room = $this->createAvailableRoom();
        $headers = $this->posAuthHeaders();

        $this->withHeaders($headers)
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'booking_type' => BookingTypeEnum::WalkIn->value,
                'charge_type' => BookingChargeTypeEnum::Session->value,
                'guest_name' => 'First Session Guest',
                'expected_check_in_at' => '2026-10-14 12:00:00',
                'expected_check_out_at' => '2026-10-14 14:00:00',
                'session_hours' => 2,
                'session_rate' => 20000,
            ])
            ->assertCreated();

        $this->withHeaders($headers)
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'booking_type' => BookingTypeEnum::WalkIn->value,
                'charge_type' => BookingChargeTypeEnum::Session->value,
                'guest_name' => 'Overlapping Session Guest',
                'expected_check_in_at' => '2026-10-14 13:00:00',
                'expected_check_out_at' => '2026-10-14 15:00:00',
                'session_hours' => 2,
                'session_rate' => 20000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room_id');

        $this->withHeaders($headers)
            ->postJson('/api/v1/pos/bookings', [
                'room_id' => $room->id,
                'booking_type' => BookingTypeEnum::WalkIn->value,
                'charge_type' => BookingChargeTypeEnum::Session->value,
                'guest_name' => 'Back To Back Session Guest',
                'expected_check_in_at' => '2026-10-14 14:00:00',
                'expected_check_out_at' => '2026-10-14 16:00:00',
                'session_hours' => 2,
                'session_rate' => 20000,
            ])
            ->assertCreated();
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

    #[TestWith([172500, true])]
    #[TestWith([150000, false])]
    public function test_checkout_includes_frontend_calculated_charges(int $paymentAmount, bool $succeeds): void
    {
        $room = $this->createAvailableRoom([
            'price' => 150000,
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

        $response = $this->withHeaders($headers)
            ->postJson("/api/v1/pos/bookings/{$bookingId}/checkout", [
                'cashbook_id' => $cashbook->id,
                'payment_method' => PaymentMethodEnum::Cash->value,
                'amount' => $paymentAmount,
                'tax_percentage' => 10,
                'tax_amount' => 15000,
                'service_charge_percentage' => 5,
                'service_charge_amount' => 7500,
            ]);

        if (! $succeeds) {
            $response->assertUnprocessable()->assertJsonValidationErrors('amount');
            $this->assertDatabaseHas('bookings', [
                'id' => $bookingId,
                'status' => BookingStatusEnum::CheckedIn->value,
                'tax_amount' => 0,
                'service_charge_amount' => 0,
                'total_amount' => 150000,
            ]);
            $this->assertDatabaseMissing('booking_payments', ['booking_id' => $bookingId]);
            $this->assertDatabaseHas('cashbooks', ['id' => $cashbook->id, 'current_balance' => 0]);

            return;
        }

        $response->assertOk()
            ->assertJsonPath('data.tax_percentage', '10.00')
            ->assertJsonPath('data.tax_amount', '15000.00')
            ->assertJsonPath('data.service_charge_percentage', '5.00')
            ->assertJsonPath('data.service_charge_amount', '7500.00')
            ->assertJsonPath('data.total_amount', '172500.00')
            ->assertJsonPath('data.balance_amount', '0.00');
        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'tax_percentage' => 10,
            'service_charge_percentage' => 5,
            'total_amount' => 172500,
            'paid_amount' => 172500,
        ]);
        $this->assertDatabaseHas('cashbooks', ['id' => $cashbook->id, 'current_balance' => 172500]);
    }

    public function test_checkout_preserves_charges_saved_on_booking(): void
    {
        $room = $this->createAvailableRoom([
            'price' => 150000,
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
                'tax_percentage' => 10,
                'tax_amount' => 15000,
                'service_charge_percentage' => 5,
                'service_charge_amount' => 7500,
            ])->json('data.id');

        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'tax_percentage' => 10,
            'service_charge_percentage' => 5,
            'total_amount' => 172500,
            'balance_amount' => 172500,
        ]);

        $this->withHeaders($headers)
            ->postJson("/api/v1/pos/bookings/{$bookingId}/checkout", [
                'cashbook_id' => $cashbook->id,
                'payment_method' => PaymentMethodEnum::Cash->value,
                'amount' => 172500,
            ])->assertOk()
            ->assertJsonPath('data.tax_amount', '15000.00')
            ->assertJsonPath('data.service_charge_amount', '7500.00')
            ->assertJsonPath('data.total_amount', '172500.00')
            ->assertJsonPath('data.balance_amount', '0.00');
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

        $this->assertDatabaseHas('cashbook_transactions', [
            'cashbook_id' => $cashbook->id,
            'transaction_type' => CashbookTransactionTypeEnum::Income->value,
            'amount' => 100,
            'balance_after' => 100,
            'reference_type' => 'booking_payment',
        ]);
        $this->assertDatabaseHas('cashbooks', [
            'id' => $cashbook->id,
            'current_balance' => 100,
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

        $this->assertDatabaseHas('cashbook_transactions', [
            'cashbook_id' => $cashbook->id,
            'transaction_type' => CashbookTransactionTypeEnum::Income->value,
            'amount' => 40,
            'balance_after' => 40,
            'reference_type' => 'booking_payment',
        ]);
        $this->assertDatabaseHas('cashbooks', [
            'id' => $cashbook->id,
            'current_balance' => 40,
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
