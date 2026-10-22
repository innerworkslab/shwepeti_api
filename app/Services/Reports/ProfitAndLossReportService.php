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
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'basis' => 'completed_bookings_and_recorded_expenses',
            'revenue' => [
                'rooms' => $this->decimal($roomRevenue),
                'food' => $this->decimal($foodRevenue),
                'services' => $this->decimal($serviceRevenue),
                'other_income' => $this->decimal($otherIncome),
                'total' => $this->decimal($totalRevenue),
            ],
            'cost_of_sales' => [
                'recorded_food_cost' => $this->decimal($recordedFoodCosts),
                'recorded_stock_out_cost' => $this->decimal($recordedStockCosts),
                'recorded_total' => $this->decimal($recordedCosts),
                'missing_cost_count' => $missingCostCount,
                'total' => $missingCostCount === 0 ? $this->decimal($recordedCosts) : null,
            ],
            'expenses' => [
                'manual_cashbook_expenses' => $this->decimal($operatingExpenses),
                'depreciation' => $this->decimal($depreciation),
                'total' => $this->decimal($totalExpenses),
            ],
            'gross_profit' => $missingCostCount === 0 ? $this->decimal($grossProfit) : null,
            'net_profit' => $missingCostCount === 0 ? $this->decimal($netProfit) : null,
            'profit_before_unrecorded_costs' => $this->decimal($netProfit),
            'has_missing_costs' => $missingCostCount > 0,
            'notes' => [
                'Room charges and non-cancelled food and service orders are recognized on booking checkout, including archived bookings. Discounts are deducted, service charges included, and sales taxes excluded.',
                'Unlinked cashbook entries are treated as other income or operating expenses on their transaction date. Booking payments, transfers, asset purchases and supplier settlements are excluded.',
                'Food usage costs follow the related booking checkout. Stock-out costs follow their transaction date. Inventory purchases, transfers and adjustments are excluded.',
                'Depreciation uses recorded entries dated within the period; monthly entries are not prorated. Unrecorded depreciation and unpaid operating expenses are not estimated.',
                'Missing inventory costs leave gross and net profit unavailable. Profit before unrecorded costs uses only recorded costs.',
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
