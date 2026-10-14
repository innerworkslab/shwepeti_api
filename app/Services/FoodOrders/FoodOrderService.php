<?php

namespace App\Services\FoodOrders;

use App\Enums\BookingStatusEnum;
use App\Enums\FoodOrderStatusEnum;
use App\Enums\RoomStatusEnum;
use App\Http\Resources\Pos\FoodOrders\PosFoodOrderResource;
use App\Models\Booking;
use App\Models\FoodOrder;
use App\Models\InventoryLedger;
use App\Models\InventoryStockBalance;
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
                $menu = Menu::query()->with('recipes')->findOrFail($line['menu_id']);
                $unitPrice = (float) $menu->price;
                $qty = (int) $line['qty'];

                if ($menu->recipes->isNotEmpty() && blank($menu->warehouse_id)) {
                    throw ValidationException::withMessages([
                        'items' => ["Menu {$menu->name} does not have a warehouse configured."],
                    ]);
                }

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
        return DB::transaction(function () use ($foodOrder, $status, $actor): PosFoodOrderResource {
            $this->ensureStatusTransition($foodOrder, $status);

            if ($status === FoodOrderStatusEnum::Preparing->value) {
                $foodOrder->load(['items.menu.recipes']);
                $this->postInventoryMovements($foodOrder, $actor);
            }

            $foodOrder->update([
                'status' => $status,
                'updated_by' => $actor?->id,
            ]);

            return new PosFoodOrderResource($foodOrder->refresh()->load(['booking', 'room.roomCategory', 'items.menu.menuCategory']));
        });
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

    private function postInventoryMovements(FoodOrder $foodOrder, ?User $actor = null): void
    {
        foreach ($foodOrder->items as $foodOrderItem) {
            $foodOrderItem->loadMissing('menu.recipes');

            foreach ($foodOrderItem->menu->recipes as $recipe) {
                if (blank($foodOrderItem->menu->warehouse_id)) {
                    throw ValidationException::withMessages([
                        'items' => ["Menu {$foodOrderItem->menu->name} does not have a warehouse configured."],
                    ]);
                }

                $baseQuantity = (float) $recipe->base_quantity * $foodOrderItem->qty;
                $balance = $this->stockBalance($recipe->item_id, $foodOrderItem->menu->warehouse_id);
                $newQuantity = (float) $balance->quantity - $baseQuantity;

                if ($newQuantity < 0) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for  {$recipe->item->name} (Menu) in warehouse {$foodOrderItem->menu->warehouse->name}."],
                    ]);
                }

                $balance->update([
                    'quantity' => $this->decimal($newQuantity),
                    'available_quantity' => $this->decimal($newQuantity - (float) $balance->reserved_quantity),
                ]);

                InventoryLedger::query()->create([
                    'item_id' => $recipe->item_id,
                    'warehouse_id' => $foodOrderItem->menu->warehouse_id,
                    'transaction_type' => 'food_order',
                    'reference_type' => $foodOrderItem->getMorphClass(),
                    'reference_id' => $foodOrderItem->id,
                    'quantity' => $this->decimal((float) $recipe->quantity * $foodOrderItem->qty),
                    'unit_id' => $recipe->unit_id,
                    'base_quantity' => $this->decimal(-1 * $baseQuantity),
                    'unit_cost' => null,
                    'total_cost' => null,
                    'batch_no' => null,
                    'expiry_date' => null,
                    'balance_quantity' => $this->decimal($newQuantity),
                    'transaction_date' => now(),
                    'remark' => "Food order {$foodOrder->order_no}",
                    'created_by' => $actor?->id,
                ]);
            }
        }
    }

    private function stockBalance(int $itemId, int $warehouseId): InventoryStockBalance
    {
        InventoryStockBalance::query()->firstOrCreate([
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
        ], [
            'quantity' => 0,
            'reserved_quantity' => 0,
            'available_quantity' => 0,
        ]);

        return InventoryStockBalance::query()
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function decimal(float $value): string
    {
        return number_format($value, 6, '.', '');
    }
}
