<?php

namespace App\Services\Reports;

use App\Enums\BookingStatusEnum;
use App\Enums\CashbookTransactionTypeEnum;
use App\Enums\FoodOrderStatusEnum;
use App\Enums\ServiceOrderStatusEnum;
use App\Models\Booking;
use App\Models\CashbookTransaction;
use App\Models\FixedAssetDepreciation;
use App\Models\FoodOrder;
use App\Models\FoodOrderItem;
use App\Models\InventoryLedger;
use App\Models\ServiceOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ProfitAndLossReportService
{
    /**
     * @param  array{date_from: string, date_to: string}  $filters
     * @return array<string, mixed>
     */
    public function get(array $filters): array
    {
        $start = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $end = CarbonImmutable::parse($filters['date_to'])->addDay()->startOfDay();
        $bookings = Booking::withTrashed()
            ->where('status', BookingStatusEnum::CheckedOut)
            ->where('checked_out_at', '>=', $start)
            ->where('checked_out_at', '<', $end);
        $bookingIds = (clone $bookings)->select('id');
        $foodOrders = FoodOrder::query()->whereIn('booking_id', $bookingIds)
            ->where('status', '!=', FoodOrderStatusEnum::Cancelled);
        $serviceOrders = ServiceOrder::query()->whereIn('booking_id', $bookingIds)
            ->where('status', '!=', ServiceOrderStatusEnum::Cancelled);

        $roomRevenue = $this->netRevenue($bookings);
        $foodRevenue = $this->netRevenue($foodOrders);
        $serviceRevenue = $this->netRevenue($serviceOrders);
        $manualTransactions = CashbookTransaction::query()
            ->whereNull('reference_type')
            ->where('transaction_date', '>=', $start)
            ->where('transaction_date', '<', $end);
        $otherIncome = (float) (clone $manualTransactions)
            ->where('transaction_type', CashbookTransactionTypeEnum::Income)->sum('amount');
        $operatingExpenses = (float) (clone $manualTransactions)
            ->where('transaction_type', CashbookTransactionTypeEnum::Expense)->sum('amount');

        $foodCosts = InventoryLedger::query()
            ->where('transaction_type', 'food_order')
            ->where('reference_type', (new FoodOrderItem)->getMorphClass())
            ->whereIn('reference_id', FoodOrderItem::query()->select('id')
                ->whereIn('food_order_id', (clone $foodOrders)->select('id')));
        $stockCosts = InventoryLedger::query()->where('transaction_type', 'out')
            ->where('transaction_date', '>=', $start)
            ->where('transaction_date', '<', $end);
        $missingCostCount = (clone $foodCosts)->whereNull('total_cost')->count()
            + (clone $stockCosts)->whereNull('total_cost')->count();
        $recordedFoodCosts = round((float) $foodCosts->sum('total_cost'), 2);
        $recordedStockCosts = round((float) $stockCosts->sum('total_cost'), 2);
        $depreciation = (float) FixedAssetDepreciation::query()
            ->where('period', '>=', $filters['date_from'])
            ->where('period', '<=', $filters['date_to'])->sum('amount');
        $totalRevenue = $roomRevenue + $foodRevenue + $serviceRevenue + $otherIncome;
        $recordedCosts = $recordedFoodCosts + $recordedStockCosts;
        $totalExpenses = $operatingExpenses + $depreciation;
        $grossProfit = $totalRevenue - $recordedCosts;
        $netProfit = $grossProfit - $totalExpenses;

        return [
            'rows' => [
                ['name' => 'Room Income', 'amount' => $this->decimal($roomRevenue)],
                ['name' => 'Food / Order Income', 'amount' => $this->decimal($foodRevenue)],
                ['name' => 'Other Income', 'amount' => $this->decimal($serviceRevenue + $otherIncome)],
                ['name' => 'Total Income', 'amount' => $this->decimal($totalRevenue)],
                ['name' => 'Operating Expense', 'amount' => $this->decimal($operatingExpenses + $recordedCosts)],
                ['name' => 'Other Expense', 'amount' => $this->decimal(0)],
                ['name' => 'Depreciation', 'amount' => $this->decimal($depreciation)],
                ['name' => 'Total Expense', 'amount' => $this->decimal($totalExpenses + $recordedCosts)],
                ['name' => 'Profit / Loss', 'amount' => $this->decimal($netProfit)],
            ],
            'meta' => [
                'current_page' => 1,
                'per_page' => 15,
                'total' => 9,
                'last_page' => 1,
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'currency' => 'MMK',
                'basis' => 'completed_bookings_and_recorded_expenses',
                'has_missing_costs' => $missingCostCount > 0,
                'missing_cost_count' => $missingCostCount,
                'recorded_total_expense' => $this->decimal($totalExpenses + $recordedCosts),
                'profit_before_unrecorded_costs' => $this->decimal($netProfit),
            ],
        ];
    }

    private function netRevenue(Builder $query): float
    {
        return round((float) (clone $query)->selectRaw('COALESCE(SUM(total_amount - tax_amount), 0) AS revenue')->toBase()->first()->revenue, 2);
    }

    private function decimal(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
