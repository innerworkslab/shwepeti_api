<?php

namespace App\Services\InventoryStockBalances;

use App\Models\InventoryStockBalance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class InventoryStockBalanceService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = InventoryStockBalance::query()->with(['item.stockUnit', 'warehouse'])->filter($filters)->latest();

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }
}
