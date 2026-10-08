<?php

namespace App\Services\FoodOrders;

use App\Enums\BookingStatusEnum;
use App\Enums\FoodOrderStatusEnum;
use App\Enums\RoomStatusEnum;
use App\Http\Resources\Pos\FoodOrders\PosFoodOrderResource;
use App\Models\Booking;
use App\Models\FoodOrder;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FoodOrderService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = FoodOrder::query()
            ->with(['booking', 'room.roomCategory', 'items.menu.menuCategory'])
            ->filter($filters)
            ->latest();

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function create(array $data, ?User $actor = null): PosFoodOrderResource
    {
        return DB::transaction(function () use ($data, $actor): PosFoodOrderResource {
            $booking = Booking::query()->with('room')->findOrFail($data['booking_id']);
            $this->ensureOrderableBooking($booking);

            $lines = collect($data['items'])->map(function (array $line): array {
                $menu = Menu::query()->findOrFail($line['menu_id']);
                $unitPrice = (float) $menu->price;
                $qty = (int) $line['qty'];

                return [
                    'menu_id' => $menu->id,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'total' => $unitPrice * $qty,
                    'note' => $line['note'] ?? null,
                ];
            });

            $subtotal = (float) $lines->sum('total');
            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $taxAmount = (float) ($data['tax_amount'] ?? 0);

            $foodOrder = FoodOrder::query()->create([
                'booking_id' => $booking->id,
                'room_id' => $booking->room_id,
                'order_no' => $this->nextOrderNo(),
                'status' => FoodOrderStatusEnum::Pending,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => max(0, $subtotal - $discountAmount + $taxAmount),
                'note' => $data['note'] ?? null,
                'created_by' => $actor?->id,
            ]);

            $foodOrder->items()->createMany($lines->all());

            return new PosFoodOrderResource($foodOrder->refresh()->load(['booking', 'room.roomCategory', 'items.menu.menuCategory']));
        });
    }

    public function updateStatus(FoodOrder $foodOrder, string $status, ?User $actor = null): PosFoodOrderResource
    {
        $this->ensureStatusTransition($foodOrder, $status);

        $foodOrder->update([
            'status' => $status,
            'updated_by' => $actor?->id,
        ]);

        return new PosFoodOrderResource($foodOrder->refresh()->load(['booking', 'room.roomCategory', 'items.menu.menuCategory']));
    }

    private function ensureOrderableBooking(Booking $booking): void
    {
        if ($booking->status !== BookingStatusEnum::CheckedIn || $booking->room?->status !== RoomStatusEnum::Occupied) {
            throw ValidationException::withMessages([
                'booking_id' => ['Food orders can only be added to checked-in bookings with occupied rooms.'],
            ]);
        }
    }

    private function ensureStatusTransition(FoodOrder $foodOrder, string $status): void
    {
        $current = $foodOrder->status;

        $allowed = match ($current) {
            FoodOrderStatusEnum::Pending => [
                FoodOrderStatusEnum::Preparing->value,
                FoodOrderStatusEnum::Cancelled->value,
            ],
            FoodOrderStatusEnum::Preparing => [
                FoodOrderStatusEnum::Served->value,
            ],
            default => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid food order status transition.'],
            ]);
        }
    }

    private function nextOrderNo(): string
    {
        return 'FO-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }
}
