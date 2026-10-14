<?php

namespace App\Services\InventoryStockBalances;

use App\Models\InventoryLedger;
use App\Models\InventoryStockBalance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class InventoryStockBalanceService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = InventoryStockBalance::query()->with(['item.stockUnit', 'warehouse'])->filter($filters)->latest();

        if (! isset($filters['page'])) {
            return $this->withMovementBalances($query->get(), $filters);
        }

        $balances = $query->paginate((int) ($filters['per_page'] ?? 15));
        $balances->setCollection($this->withMovementBalances($balances->getCollection(), $filters));

        return $balances;
    }

    /**
     * @param  Collection<int, InventoryStockBalance>  $balances
     * @param  array<string, mixed>  $filters
     * @return EloquentCollection<int, InventoryStockBalance>
     */
    private function withMovementBalances(Collection $balances, array $filters): EloquentCollection
    {
        if ($balances->isEmpty()) {
            return new EloquentCollection;
        }

        if (blank($filters['date_from'] ?? null) && blank($filters['date_to'] ?? null)) {
            return new EloquentCollection($balances->map(function (InventoryStockBalance $balance): InventoryStockBalance {
                $quantity = $this->decimal((float) $balance->quantity);

                $balance->setAttribute('opening_balance', $quantity);
                $balance->setAttribute('in_balance', $this->decimal(0));
                $balance->setAttribute('out_balance', $this->decimal(0));
                $balance->setAttribute('closing_balance', $quantity);

                return $balance;
            })->all());
        }

        $itemIds = $balances->pluck('item_id')->unique()->all();
        $warehouseIds = $balances->pluck('warehouse_id')->unique()->all();

        $openingBalances = $this->ledgerTotals($itemIds, $warehouseIds, null, $filters['date_from'] ?? null, '<');
        $periodBalances = $this->ledgerTotals($itemIds, $warehouseIds, $filters['date_from'] ?? null, $filters['date_to'] ?? null);

        return new EloquentCollection($balances->map(function (InventoryStockBalance $balance) use ($openingBalances, $periodBalances): InventoryStockBalance {
            $key = "{$balance->item_id}:{$balance->warehouse_id}";
            $openingBalance = (float) ($openingBalances[$key]['net_balance'] ?? 0);
            $inBalance = (float) ($periodBalances[$key]['in_balance'] ?? 0);
            $outBalance = (float) ($periodBalances[$key]['out_balance'] ?? 0);
            $closingBalance = $openingBalance + $inBalance - $outBalance;

            $balance->setAttribute('opening_balance', $this->decimal($openingBalance));
            $balance->setAttribute('in_balance', $this->decimal($inBalance));
            $balance->setAttribute('out_balance', $this->decimal($outBalance));
            $balance->setAttribute('closing_balance', $this->decimal($closingBalance));

            return $balance;
        })->all());
    }

    /**
     * @param  array<int, int>  $itemIds
     * @param  array<int, int>  $warehouseIds
     * @return array<string, array{in_balance: string, out_balance: string, net_balance: string}>
     */
    private function ledgerTotals(array $itemIds, array $warehouseIds, mixed $dateFrom = null, mixed $dateTo = null, string $dateToOperator = '<='): array
    {
        return InventoryLedger::query()
            ->selectRaw('item_id, warehouse_id')
            ->selectRaw('COALESCE(SUM(CASE WHEN base_quantity > 0 THEN base_quantity ELSE 0 END), 0) as in_balance')
            ->selectRaw('COALESCE(SUM(CASE WHEN base_quantity < 0 THEN ABS(base_quantity) ELSE 0 END), 0) as out_balance')
            ->selectRaw('COALESCE(SUM(base_quantity), 0) as net_balance')
            ->whereIn('item_id', $itemIds)
            ->whereIn('warehouse_id', $warehouseIds)
            ->when(filled($dateFrom), fn ($query) => $query->whereDate('transaction_date', '>=', $dateFrom))
            ->when(filled($dateTo), fn ($query) => $query->whereDate('transaction_date', $dateToOperator, $dateTo))
            ->groupBy('item_id', 'warehouse_id')
            ->get()
            ->mapWithKeys(fn (InventoryLedger $ledger): array => [
                "{$ledger->item_id}:{$ledger->warehouse_id}" => [
                    'in_balance' => (string) $ledger->in_balance,
                    'out_balance' => (string) $ledger->out_balance,
                    'net_balance' => (string) $ledger->net_balance,
                ],
            ])
            ->all();
    }

    private function decimal(float $value): string
    {
        return number_format($value, 6, '.', '');
    }
}
