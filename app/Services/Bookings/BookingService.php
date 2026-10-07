<?php

namespace App\Services\Bookings;

use App\Enums\BookingChargeTypeEnum;
use App\Enums\BookingPaymentTypeEnum;
use App\Enums\BookingStatusEnum;
use App\Enums\BookingTypeEnum;
use App\Enums\RoomStatusEnum;
use App\Http\Resources\Pos\Bookings\PosBookingResource;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = Booking::query()
            ->with(['room.roomCategory', 'payments'])
            ->filter($filters)
            ->latest();

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function create(array $data, ?User $actor = null): PosBookingResource
    {
        return DB::transaction(function () use ($data, $actor): PosBookingResource {
            /** @var Room $room */
            $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);

            if ($room->status !== RoomStatusEnum::Available) {
                throw ValidationException::withMessages([
                    'room_id' => ['This room is not available for booking.'],
                ]);
            }

            $expectedCheckInAt = Carbon::parse($data['expected_check_in_at']);
            $expectedCheckOutAt = Carbon::parse($data['expected_check_out_at']);
            $chargeType = $data['charge_type'] ?? BookingChargeTypeEnum::Day->value;
            $nights = max(1, (int) ceil($expectedCheckInAt->diffInDays($expectedCheckOutAt)));
            $roomRate = (float) $room->price;
            $sessionRate = isset($data['session_rate']) ? (float) $data['session_rate'] : null;
            $subtotal = $chargeType === BookingChargeTypeEnum::Session->value ? (float) $sessionRate : $roomRate * $nights;
            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $taxAmount = (float) ($data['tax_amount'] ?? 0);
            $totalAmount = max(0, $subtotal - $discountAmount + $taxAmount);
            $depositAmount = (float) data_get($data, 'deposit.amount', 0);
            $paidAmount = min($depositAmount, $totalAmount);
            $balanceAmount = max(0, $totalAmount - $paidAmount);
            $bookingType = $data['booking_type'] ?? null;
            $checkInNow = filter_var($data['check_in_now'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $bookingType ??= $checkInNow ? BookingTypeEnum::WalkIn->value : BookingTypeEnum::Reservation->value;
            $checkInNow = $bookingType === BookingTypeEnum::WalkIn->value || $checkInNow;

            if ($depositAmount > 0 && $bookingType !== BookingTypeEnum::Reservation->value) {
                throw ValidationException::withMessages([
                    'deposit.amount' => ['Deposit payments can only be added to reservation bookings.'],
                ]);
            }

            if ($depositAmount > $totalAmount) {
                throw ValidationException::withMessages([
                    'deposit.amount' => ['Deposit amount cannot be greater than the booking total.'],
                ]);
            }

            $booking = Booking::query()->create([
                'booking_no' => $this->nextBookingNo(),
                'room_id' => $room->id,
                'booking_type' => $bookingType,
                'charge_type' => $chargeType,
                'guest_name' => $data['guest_name'],
                'guest_phone' => $data['guest_phone'] ?? null,
                'guest_email' => $data['guest_email'] ?? null,
                'expected_check_in_at' => $expectedCheckInAt,
                'expected_check_out_at' => $expectedCheckOutAt,
                'checked_in_at' => $checkInNow ? now() : null,
                'status' => $checkInNow ? BookingStatusEnum::CheckedIn : BookingStatusEnum::Reserved,
                'guest_count' => $data['guest_count'] ?? 1,
                'room_rate' => $roomRate,
                'session_hours' => $chargeType === BookingChargeTypeEnum::Session->value ? $data['session_hours'] : null,
                'session_rate' => $chargeType === BookingChargeTypeEnum::Session->value ? $sessionRate : null,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'balance_amount' => $balanceAmount,
                'note' => $data['note'] ?? null,
                'created_by' => $actor?->id,
                'checked_in_by' => $checkInNow ? $actor?->id : null,
            ]);

            if ($depositAmount > 0) {
                BookingPayment::query()->create([
                    'booking_id' => $booking->id,
                    'cashbook_id' => data_get($data, 'deposit.cashbook_id'),
                    'payment_no' => $this->nextPaymentNo(),
                    'payment_type' => BookingPaymentTypeEnum::Deposit,
                    'payment_method' => data_get($data, 'deposit.payment_method'),
                    'amount' => $depositAmount,
                    'paid_at' => data_get($data, 'deposit.paid_at') ? Carbon::parse(data_get($data, 'deposit.paid_at')) : now(),
                    'note' => data_get($data, 'deposit.note'),
                    'created_by' => $actor?->id,
                ]);
            }

            $room->update([
                'status' => $checkInNow ? RoomStatusEnum::Occupied : RoomStatusEnum::Reserved,
                'updated_by' => $actor?->id,
            ]);

            return new PosBookingResource($booking->refresh()->load(['room.roomCategory', 'payments']));
        });
    }

    public function checkIn(Booking $booking, ?User $actor = null): PosBookingResource
    {
        return DB::transaction(function () use ($booking, $actor): PosBookingResource {
            $booking->load('room');

            if ($booking->status !== BookingStatusEnum::Reserved) {
                throw ValidationException::withMessages([
                    'booking' => ['Only reserved bookings can be checked in.'],
                ]);
            }

            $booking->update([
                'status' => BookingStatusEnum::CheckedIn,
                'checked_in_at' => now(),
                'checked_in_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $booking->room->update([
                'status' => RoomStatusEnum::Occupied,
                'updated_by' => $actor?->id,
            ]);

            return new PosBookingResource($booking->refresh()->load(['room.roomCategory', 'payments']));
        });
    }

    public function checkOut(Booking $booking, ?User $actor = null): PosBookingResource
    {
        return DB::transaction(function () use ($booking, $actor): PosBookingResource {
            $booking->load('room');

            if ($booking->status !== BookingStatusEnum::CheckedIn) {
                throw ValidationException::withMessages([
                    'booking' => ['Only checked-in bookings can be checked out.'],
                ]);
            }

            $booking->update([
                'status' => BookingStatusEnum::CheckedOut,
                'checked_out_at' => now(),
                'checked_out_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $booking->room->update([
                'status' => RoomStatusEnum::Dirty,
                'updated_by' => $actor?->id,
            ]);

            return new PosBookingResource($booking->refresh()->load(['room.roomCategory', 'payments']));
        });
    }

    public function checkoutWithPayment(Booking $booking, array $data, ?User $actor = null): PosBookingResource
    {
        return DB::transaction(function () use ($booking, $data, $actor): PosBookingResource {
            $booking = Booking::query()
                ->with('room')
                ->lockForUpdate()
                ->findOrFail($booking->id);

            if ($booking->status !== BookingStatusEnum::CheckedIn) {
                throw ValidationException::withMessages([
                    'booking' => ['Only checked-in bookings can be checked out.'],
                ]);
            }

            $amount = (float) $data['amount'];

            if ($amount < (float) $booking->balance_amount) {
                throw ValidationException::withMessages([
                    'amount' => ['Checkout payment must cover the remaining balance.'],
                ]);
            }

            BookingPayment::query()->create([
                'booking_id' => $booking->id,
                'cashbook_id' => $data['cashbook_id'],
                'payment_no' => $this->nextPaymentNo(),
                'payment_type' => BookingPaymentTypeEnum::Checkout,
                'payment_method' => $data['payment_method'],
                'amount' => $amount,
                'paid_at' => isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now(),
                'note' => $data['note'] ?? null,
                'created_by' => $actor?->id,
            ]);

            $paidAmount = (float) $booking->paid_amount + $amount;

            $booking->update([
                'paid_amount' => $paidAmount,
                'balance_amount' => max(0, (float) $booking->total_amount - $paidAmount),
                'status' => BookingStatusEnum::CheckedOut,
                'checked_out_at' => now(),
                'checked_out_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $booking->room->update([
                'status' => RoomStatusEnum::Dirty,
                'updated_by' => $actor?->id,
            ]);

            return new PosBookingResource($booking->refresh()->load(['room.roomCategory', 'payments']));
        });
    }

    public function addPartialPayment(Booking $booking, array $data, ?User $actor = null): PosBookingResource
    {
        return DB::transaction(function () use ($booking, $data, $actor): PosBookingResource {
            $booking = Booking::query()
                ->with('room')
                ->lockForUpdate()
                ->findOrFail($booking->id);

            if ($booking->status !== BookingStatusEnum::CheckedIn) {
                throw ValidationException::withMessages([
                    'booking' => ['Only checked-in bookings can receive partial payments.'],
                ]);
            }

            $amount = (float) $data['amount'];

            if ($amount > (float) $booking->balance_amount) {
                throw ValidationException::withMessages([
                    'amount' => ['Partial payment cannot be greater than the remaining balance.'],
                ]);
            }

            BookingPayment::query()->create([
                'booking_id' => $booking->id,
                'cashbook_id' => $data['cashbook_id'],
                'payment_no' => $this->nextPaymentNo(),
                'payment_type' => BookingPaymentTypeEnum::Partial,
                'payment_method' => $data['payment_method'],
                'amount' => $amount,
                'paid_at' => isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now(),
                'note' => $data['note'] ?? null,
                'created_by' => $actor?->id,
            ]);

            $paidAmount = (float) $booking->paid_amount + $amount;

            $booking->update([
                'paid_amount' => $paidAmount,
                'balance_amount' => max(0, (float) $booking->total_amount - $paidAmount),
                'updated_by' => $actor?->id,
            ]);

            return new PosBookingResource($booking->refresh()->load(['room.roomCategory', 'payments']));
        });
    }

    public function cancel(Booking $booking, ?User $actor = null): PosBookingResource
    {
        return DB::transaction(function () use ($booking, $actor): PosBookingResource {
            $booking->load('room');

            if ($booking->status !== BookingStatusEnum::Reserved) {
                throw ValidationException::withMessages([
                    'booking' => ['Only reserved bookings can be cancelled.'],
                ]);
            }

            $booking->update([
                'status' => BookingStatusEnum::Cancelled,
                'updated_by' => $actor?->id,
            ]);

            $booking->room->update([
                'status' => RoomStatusEnum::Available,
                'updated_by' => $actor?->id,
            ]);

            return new PosBookingResource($booking->refresh()->load(['room.roomCategory', 'payments']));
        });
    }

    private function nextBookingNo(): string
    {
        return 'BK-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }

    private function nextPaymentNo(): string
    {
        return 'BP-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }
}
