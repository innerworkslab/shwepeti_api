<?php

namespace App\Services\ServiceOrders;

use App\Enums\BookingStatusEnum;
use App\Enums\RoomStatusEnum;
use App\Enums\ServiceOrderStatusEnum;
use App\Enums\ServiceOrderTypeEnum;
use App\Http\Resources\Pos\ServiceOrders\PosServiceOrderResource;
use App\Models\Booking;
use App\Models\Item;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceOrderService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = ServiceOrder::query()
            ->with(['booking', 'room.roomCategory', 'items.item.itemCategory'])
            ->filter($filters)
            ->latest();

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function create(array $data, ?User $actor = null): PosServiceOrderResource
    {
        return DB::transaction(function () use ($data, $actor): PosServiceOrderResource {
            $booking = Booking::query()->with('room')->findOrFail($data['booking_id']);
            $this->ensureOrderableBooking($booking);

            $lines = collect($data['items'])->map(function (array $line) use ($data): array {
                $item = Item::query()->with('itemCategory')->findOrFail($line['item_id']);
                $this->ensureItemMatchesType($item, $data['type']);

                $unitPrice = (float) ($item->price ?? 0);
                $qty = (int) $line['qty'];

                return [
                    'item_id' => $item->id,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'total' => $unitPrice * $qty,
                    'note' => $line['note'] ?? null,
                ];
            });

            $subtotal = (float) $lines->sum('total');
            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $taxAmount = (float) ($data['tax_amount'] ?? 0);

            $serviceOrder = ServiceOrder::query()->create([
                'booking_id' => $booking->id,
                'room_id' => $booking->room_id,
                'order_no' => $this->nextOrderNo(),
                'type' => $data['type'],
                'status' => ServiceOrderStatusEnum::Pending,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => max(0, $subtotal - $discountAmount + $taxAmount),
                'note' => $data['note'] ?? null,
                'created_by' => $actor?->id,
            ]);

            $serviceOrder->items()->createMany($lines->all());

            return new PosServiceOrderResource($serviceOrder->refresh()->load(['booking', 'room.roomCategory', 'items.item.itemCategory']));
        });
    }

    public function updateStatus(ServiceOrder $serviceOrder, string $status, ?User $actor = null): PosServiceOrderResource
    {
        $this->ensureStatusTransition($serviceOrder, $status);

        $serviceOrder->update([
            'status' => $status,
            'updated_by' => $actor?->id,
        ]);

        return new PosServiceOrderResource($serviceOrder->refresh()->load(['booking', 'room.roomCategory', 'items.item.itemCategory']));
    }

    private function ensureOrderableBooking(Booking $booking): void
    {
        if ($booking->status !== BookingStatusEnum::CheckedIn || $booking->room?->status !== RoomStatusEnum::Occupied) {
            throw ValidationException::withMessages([
                'booking_id' => ['Service orders can only be added to checked-in bookings with occupied rooms.'],
            ]);
        }
    }

    private function ensureStatusTransition(ServiceOrder $serviceOrder, string $status): void
    {
        $current = $serviceOrder->status;

        $allowed = match ($current) {
            ServiceOrderStatusEnum::Pending => [
                ServiceOrderStatusEnum::Processing->value,
                ServiceOrderStatusEnum::Cancelled->value,
            ],
            ServiceOrderStatusEnum::Processing => [
                ServiceOrderStatusEnum::Completed->value,
            ],
            default => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid service order status transition.'],
            ]);
        }
    }

    private function ensureItemMatchesType(Item $item, string $type): void
    {
        $categoryCode = $item->itemCategory?->code;
        $expectedCode = match ($type) {
            ServiceOrderTypeEnum::Amenity->value => 'AMENITIES',
            ServiceOrderTypeEnum::Laundry->value => 'LAUNDRY',
            default => null,
        };

        if ($categoryCode !== $expectedCode) {
            throw ValidationException::withMessages([
                'items' => ['One or more selected items do not match the service order type.'],
            ]);
        }
    }

    private function nextOrderNo(): string
    {
        return 'SO-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }
}
