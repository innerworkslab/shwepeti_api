<?php

namespace App\Services\Reports;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\FixedAssetDepreciation;
use App\Models\FoodOrder;
use App\Models\InventoryLedger;
use App\Models\Item;
use App\Models\Room;
use App\Models\ServiceOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class HotelReportService
{
    /**
     * @param  array{date?: string, date_from?: string, date_to?: string, room_id?: int, room_category_id?: int, item_category_id?: int, warehouse_id?: int, page?: int, per_page?: int}  $filters
     * @return array<string, mixed>
     */
    public function get(string $report, array $filters): array
    {
        $start = CarbonImmutable::parse($filters['date'] ?? $filters['date_from'] ?? today()->toDateString())->startOfDay();
        $end = CarbonImmutable::parse($filters['date'] ?? $filters['date_to'] ?? today()->toDateString())->addDay()->startOfDay();
        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['date_to' => ['The report period must not exceed 366 days.']]);
        }

        $result = match ($report) {
            'daily-summary' => $this->dailySummary($start, $end),
            'room-occupancy' => $this->occupancy($start, $end, $filters),
            'room-revenue' => $this->roomRevenue($start, $end, $filters),
            'guest-balances' => $this->guestBalances($filters),
            'food-sales' => $this->foodSales($start, $end, $filters),
            'top-selling-items' => $this->topSellingItems($start, $end, $filters),
            'other-bills' => $this->otherBills($start, $end, $filters),
            'inventory-balance' => $this->inventory($start, $end, $filters, false),
            'low-stock' => $this->inventory($start, $end, $filters, true),
            'depreciation' => $this->depreciation($start, $end),
        };
        $rows = collect($result['rows']);
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 15);

        return array_merge($result, [
            'report' => $report,
            'date_from' => $start->toDateString(),
            'date_to' => $end->subDay()->toDateString(),
            'currency' => 'MMK',
            'rows' => $rows->forPage($page, $perPage)->values()->all(),
            'pagination' => ['current_page' => $page, 'per_page' => $perPage, 'total' => $rows->count(), 'last_page' => max(1, (int) ceil($rows->count() / $perPage))],
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function bookings(array $filters): Builder
    {
        return Booking::withTrashed()->with([
            'room' => fn ($query) => $query->withTrashed(),
            'room.roomCategory' => fn ($query) => $query->withTrashed(),
        ])->when($filters['room_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('room_id', $id))
            ->when($filters['room_category_id'] ?? null, fn (Builder $query, int|string $id) => $query->whereHas('room', fn (Builder $room) => $room->withTrashed()->where('room_category_id', $id)));
    }

    private function period(Builder $query, string $column, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return $query->where($column, '>=', $start)->where($column, '<', $end);
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function occupancy(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        $cutoff = $end->min(CarbonImmutable::now());
        $rows = $this->bookings($filters)->whereIn('status', ['checked_in', 'checked_out'])
            ->where('checked_in_at', '<', $end)
            ->where(fn (Builder $query) => $query->whereNull('checked_out_at')->orWhere('checked_out_at', '>', $start))
            ->orderBy('checked_in_at')->orderBy('id')->get()->map(function (Booking $booking) use ($cutoff): array {
                $finish = $booking->checked_out_at?->toImmutable()->min($cutoff) ?? $cutoff;

                return [
                    'booking_id' => $booking->id, 'room' => $booking->room?->name, 'category' => $booking->room?->roomCategory?->name,
                    'guest' => $booking->guest_name, 'check_in' => $booking->checked_in_at?->toDateTimeString(),
                    'check_out' => $booking->checked_out_at?->toDateTimeString(),
                    'duration_days' => max(0, (int) ceil($booking->checked_in_at->diffInDays($finish))),
                    'status' => $booking->checked_out_at && $booking->checked_out_at->lte($cutoff) ? 'checked_out' : 'occupied',
                ];
            });

        return ['rows' => $rows->all(), 'notes' => ['Actual stays overlapping the period. Duration runs from check-in to checkout or the earlier of now and the period end.']];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function roomRevenue(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        $rows = $this->period($this->bookings($filters)->where('status', 'checked_out'), 'checked_out_at', $start, $end)
            ->orderBy('checked_out_at')->orderBy('id')->get()->map(fn (Booking $booking): array => [
                'booking_id' => $booking->id, 'date' => $booking->checked_out_at->toDateString(), 'room' => $booking->room?->name,
                'guest' => $booking->guest_name, 'stay_type' => $booking->charge_type->value,
                'room_charge' => $booking->subtotal, 'extra_time' => null, 'discount' => $booking->discount_amount,
                'tax' => $booking->tax_amount, 'service_charge' => $booking->service_charge_amount, 'total' => $booking->total_amount,
            ]);

        return ['rows' => $rows->all(), 'summary' => ['total_room_revenue' => $this->decimal($rows->sum('total'))],
            'notes' => ['Room revenue uses checkout date and billed totals including tax and service charge, less discounts. Extra-time charges are not stored separately.']];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function guestBalances(array $filters): array
    {
        $rows = $this->bookings($filters)->where('status', 'checked_in')->with(['payments', 'foodOrders', 'serviceOrders'])
            ->orderBy('room_id')->orderBy('id')->get()->map(fn (Booking $booking): array => $this->balanceRow($booking));

        return ['rows' => $rows->all(), 'summary' => ['total_outstanding' => $this->decimal($rows->sum('outstanding'))],
            'notes' => ['Current checked-in guests, including fully paid guests. Date filters do not change this live report. Deposits and other payments are separate; refunds reduce net receipts.']];
    }

    /** @return array<string, mixed> */
    private function balanceRow(Booking $booking): array
    {
        $charges = (float) $booking->total_amount
            + $booking->foodOrders->reject(fn (FoodOrder $order): bool => $order->status->value === 'cancelled')->sum('total_amount')
            + $booking->serviceOrders->reject(fn (ServiceOrder $order): bool => $order->status->value === 'cancelled')->sum('total_amount');
        $deposit = $booking->payments->where('payment_type.value', 'deposit')->sum('amount');
        $paid = $booking->payments->whereIn('payment_type.value', ['partial', 'checkout'])->sum('amount');
        $refund = $booking->payments->where('payment_type.value', 'refund')->sum('amount');
        $net = $deposit + $paid - $refund;
        $outstanding = max(0, round($charges - $net, 2));

        return ['booking_id' => $booking->id, 'room' => $booking->room?->name, 'guest' => $booking->guest_name,
            'total_charges' => $this->decimal($charges), 'deposit' => $this->decimal($deposit), 'paid' => $this->decimal($paid),
            'refund' => $this->decimal($refund), 'outstanding' => $this->decimal($outstanding),
            'payment_status' => $outstanding <= 0 ? 'paid' : ($net > 0 ? 'partial' : 'unpaid')];
    }

    /** @param array<string, mixed> $filters */
    private function orders(string $model, CarbonImmutable $start, CarbonImmutable $end, array $filters): Builder
    {
        return $this->period($model::query()->where('status', '!=', 'cancelled'), 'created_at', $start, $end)
            ->when($filters['room_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('room_id', $id))
            ->with(['room' => fn ($query) => $query->withTrashed(), 'booking' => fn ($query) => $query->withTrashed()])
            ->orderBy('created_at')->orderBy('id');
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function foodSales(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        $orders = $this->orders(FoodOrder::class, $start, $end, $filters)->with('items.menu.menuCategory')->get();
        $rows = $orders->flatMap(fn (FoodOrder $order): Collection => $order->items->map(fn ($item): array => [
            'date' => $order->created_at->toDateString(), 'order_no' => $order->order_no, 'room' => $order->room?->name,
            'menu_id' => $item->menu_id, 'menu_item' => $item->menu?->name, 'category_id' => $item->menu?->menu_category_id,
            'category' => $item->menu?->menuCategory?->name, 'qty' => $item->qty, 'unit_price' => $item->unit_price,
            'amount' => $item->total, 'status' => $order->status->value,
        ]));
        $categories = $rows->groupBy('category_id')->map(fn (Collection $items): array => [
            'category_id' => $items->first()['category_id'], 'category' => $items->first()['category'], 'sales' => $this->decimal($items->sum('amount')),
        ])->values();

        return ['rows' => $rows->all(), 'summary' => [
            'category_sales' => $categories->all(), 'item_sales' => $this->decimal($rows->sum('amount')),
            'room_service' => $this->decimal($orders->sum('service_charge_amount')),
            'discount' => $this->decimal($orders->sum('discount_amount')), 'tax' => $this->decimal($orders->sum('tax_amount')),
            'total_order_revenue' => $this->decimal($orders->sum('total_amount')),
        ], 'notes' => ['Non-cancelled orders by order creation date. Category sales are gross item totals; room service is the recorded order service charge. Food/drink grouping follows configured menu categories.']];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function topSellingItems(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        $sales = $this->foodSales($start, $end, $filters);
        $rows = collect($sales['rows'])->groupBy('menu_id')->map(fn (Collection $items): array => [
            'menu_id' => $items->first()['menu_id'], 'item' => $items->first()['menu_item'],
            'qty_sold' => $items->sum('qty'), 'revenue' => $this->decimal($items->sum('amount')),
        ])->sortBy([['qty_sold', 'desc'], ['menu_id', 'asc']])->values();

        return ['rows' => $rows->all(), 'notes' => ['Ranked by quantity, excluding cancelled orders. Revenue is gross item sales before order-level discounts, tax and service charges.']];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function otherBills(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        $orders = $this->orders(ServiceOrder::class, $start, $end, $filters)->with('items.item')->get();
        $rows = $orders->flatMap(fn (ServiceOrder $order): Collection => $order->items->map(fn ($item): array => [
            'date' => $order->created_at->toDateString(), 'order_no' => $order->order_no, 'room' => $order->room?->name,
            'guest' => $order->booking?->guest_name, 'charge_type' => $order->type->value,
            'description' => $item->note ?? $item->item?->name, 'qty' => $item->qty, 'amount' => $item->total,
        ]));

        return ['rows' => $rows->all(), 'summary' => ['item_total' => $this->decimal($rows->sum('amount')), 'total_other_bill_revenue' => $this->decimal($orders->sum('total_amount'))],
            'notes' => ['Non-cancelled service orders by creation date. Rows show gross item amounts; the revenue total includes order discounts and tax.']];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function inventory(CarbonImmutable $start, CarbonImmutable $end, array $filters, bool $lowStock): array
    {
        $items = Item::query()->with(['itemCategory', 'stockUnit'])->filter($filters)->orderBy('id');
        if ($lowStock) {
            $items->where('is_active', true)->withSum(['stockBalances as current_stock' => fn (Builder $query) => $query->when($filters['warehouse_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('warehouse_id', $id))], 'quantity');
        }
        $movements = $lowStock ? collect() : InventoryLedger::query()
            ->where('transaction_date', '<', $end)
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('warehouse_id', $id))
            ->select('item_id')
            ->selectRaw('SUM(CASE WHEN transaction_date < ? THEN base_quantity ELSE 0 END) as opening', [$start])
            ->selectRaw("SUM(CASE WHEN transaction_date >= ? AND transaction_type IN ('in', 'transfer_in') THEN base_quantity ELSE 0 END) as stock_in", [$start])
            ->selectRaw("SUM(CASE WHEN transaction_date >= ? AND transaction_type IN ('out', 'transfer_out', 'food_order') THEN -base_quantity ELSE 0 END) as stock_out", [$start])
            ->selectRaw("SUM(CASE WHEN transaction_date >= ? AND transaction_type LIKE 'adjustment_%' THEN base_quantity ELSE 0 END) as adjustment", [$start])
            ->selectRaw('SUM(base_quantity) as current_stock')->groupBy('item_id')->get()->keyBy('item_id');
        $rows = $items->get()->map(function (Item $item) use ($movements, $lowStock): array {
            $movement = $movements->get($item->id);
            $stock = (float) ($lowStock ? $item->current_stock : $movement?->current_stock);
            $status = $stock <= 0 ? 'out_of_stock' : ($stock <= (float) $item->min_stock ? 'low_stock' : 'in_stock');
            $row = ['item_id' => $item->id, 'item' => $item->name, 'category' => $item->itemCategory?->name,
                'unit' => $item->stockUnit?->symbol, 'current_stock' => $this->decimal($stock, 6),
                'minimum_stock' => $item->min_stock, 'status' => $status];
            if (! $lowStock) {
                foreach (['opening', 'stock_in', 'stock_out', 'adjustment'] as $field) {
                    $row[$field] = $this->decimal((float) ($movement?->$field ?? 0), 6);
                }
            }

            return $row;
        })->filter(fn (array $row): bool => ! $lowStock || $row['status'] !== 'in_stock')->values();

        return ['rows' => $rows->all(), 'notes' => [$lowStock
            ? 'Live stock for active items, including items with no stock records. Date filters do not change this report. Stock at or below the minimum is low.'
            : 'Opening and closing quantities come from posted ledger movements in stock units. Transfers are included in stock in/out. Warehouse filter is optional; without it quantities are combined across warehouses.']];
    }

    /** @return array<string, mixed> */
    private function depreciation(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = FixedAssetDepreciation::query()->with('fixedAsset')
            ->where('period', '>=', $start->startOfMonth()->toDateString())->where('period', '<', $end->subDay()->startOfMonth()->addMonth()->toDateString())
            ->orderBy('period')->orderBy('fixed_asset_id')->get()->map(fn (FixedAssetDepreciation $entry): array => [
                'asset_id' => $entry->fixed_asset_id, 'asset' => $entry->fixedAsset?->name,
                'original_value' => $entry->fixedAsset?->purchase_amount, 'month' => $entry->period->format('Y-m'),
                'monthly_depreciation' => $entry->amount,
                'accumulated' => $this->decimal((float) $entry->fixedAsset?->purchase_amount - (float) $entry->closing_book_value),
                'book_value' => $entry->closing_book_value,
            ]);

        return ['rows' => $rows->all(), 'summary' => ['total_depreciation' => $this->decimal($rows->sum('monthly_depreciation'))],
            'notes' => ['Recorded depreciation for months touched by the date range. Book values use each recorded month-end snapshot. No depreciation is generated by this report.']];
    }

    /** @return array<string, mixed> */
    private function dailySummary(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $bookings = $this->bookings([])->where('status', '!=', 'cancelled')->where('created_at', '<', $end)->with([
            'payments' => fn ($query) => $query->where('paid_at', '<', $end),
            'foodOrders' => fn ($query) => $query->where('created_at', '<', $end),
            'serviceOrders' => fn ($query) => $query->where('created_at', '<', $end),
        ])->get();
        $rooms = Room::withTrashed()->where('created_at', '<', $end)->get();
        $foodByDate = $this->dailyTotals(FoodOrder::query()->where('status', '!=', 'cancelled'), 'created_at', 'total_amount', $start, $end);
        $otherByDate = $this->dailyTotals(ServiceOrder::query()->where('status', '!=', 'cancelled'), 'created_at', 'total_amount', $start, $end);
        $receiptsByDate = $this->dailyTotals(BookingPayment::query()->where('payment_type', '!=', 'refund'), 'paid_at', 'amount', $start, $end);
        $refundsByDate = $this->dailyTotals(BookingPayment::query()->where('payment_type', 'refund'), 'paid_at', 'amount', $start, $end);
        $rows = [];
        for ($day = $start; $day->lt($end); $day = $day->addDay()) {
            $next = $day->addDay();
            $checkouts = $bookings->filter(fn (Booking $booking): bool => $booking->status->value === 'checked_out'
                && $booking->checked_out_at?->gte($day) && $booking->checked_out_at?->lt($next));
            $roomRevenue = $checkouts->sum('total_amount');
            $foodRevenue = (float) ($foodByDate[$day->toDateString()] ?? 0);
            $otherRevenue = (float) ($otherByDate[$day->toDateString()] ?? 0);
            $received = (float) ($receiptsByDate[$day->toDateString()] ?? 0);
            $refunds = (float) ($refundsByDate[$day->toDateString()] ?? 0);
            $snapshot = $day->isToday() ? CarbonImmutable::now() : $next;
            $occupied = $bookings->filter(fn (Booking $booking): bool => $booking->checked_in_at?->lt($snapshot)
                && (! $booking->checked_out_at || $booking->checked_out_at->gte($snapshot)))->pluck('room_id')->unique()->count();
            $balances = $bookings->filter(fn (Booking $booking): bool => $booking->created_at->lt($next))
                ->sum(function (Booking $booking) use ($next): float {
                    $snapshot = clone $booking;
                    $snapshot->setRelation('payments', $booking->payments->filter(fn (BookingPayment $payment): bool => $payment->paid_at->lt($next)));
                    $snapshot->setRelation('foodOrders', $booking->foodOrders->filter(fn (FoodOrder $order): bool => $order->created_at->lt($next)));
                    $snapshot->setRelation('serviceOrders', $booking->serviceOrders->filter(fn (ServiceOrder $order): bool => $order->created_at->lt($next)));

                    return (float) $this->balanceRow($snapshot)['outstanding'];
                });
            $totalRooms = $rooms->filter(fn (Room $room): bool => $room->created_at->lt($next) && (! $room->deleted_at || $room->deleted_at->gte($next)))->count();
            $rows[] = [
                'date' => $day->toDateString(),
                'total_rooms' => $totalRooms,
                'available_rooms' => $totalRooms - $occupied,
                'occupied_rooms' => $occupied,
                'check_ins' => $bookings->filter(fn (Booking $booking): bool => $booking->checked_in_at?->gte($day) && $booking->checked_in_at?->lt($next))->count(),
                'check_outs' => $checkouts->count(),
                'room_revenue' => $this->decimal($roomRevenue), 'food_drink_revenue' => $this->decimal($foodRevenue),
                'other_bill_revenue' => $this->decimal($otherRevenue), 'total_revenue' => $this->decimal($roomRevenue + $foodRevenue + $otherRevenue),
                'total_payment_received' => $this->decimal($received), 'refunds' => $this->decimal($refunds),
                'net_payment_received' => $this->decimal($received - $refunds), 'outstanding_balance' => $this->decimal($balances),
            ];
        }

        return ['rows' => $rows, 'notes' => [
            'Room revenue is billed on checkout date; food and other bills on order creation date. Totals include tax and service charges after discounts. Cancelled records are excluded.',
            'Receipts use payment date and include deposits; refunds are separate. Outstanding is the sum of positive booking balances, not revenue minus receipts.',
            'Outstanding uses currently recorded booking charges and orders/payments up to each day end; later edits to charges cannot be reconstructed.',
            'Occupancy is at day end, or now for today. Available rooms equal total rooms minus occupied rooms, including unoccupied rooms in housekeeping or maintenance states.',
        ]];
    }

    /** @return Collection<string, string> */
    private function dailyTotals(Builder $query, string $dateColumn, string $amountColumn, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return $this->period($query, $dateColumn, $start, $end)
            ->selectRaw("DATE({$dateColumn}) as report_date, SUM({$amountColumn}) as total")
            ->groupByRaw("DATE({$dateColumn})")->pluck('total', 'report_date');
    }

    private function decimal(float|int|string $amount, int $precision = 2): string
    {
        return number_format((float) $amount, $precision, '.', '');
    }
}
