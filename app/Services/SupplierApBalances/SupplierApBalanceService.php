<?php

namespace App\Services\SupplierApBalances;

use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SupplierApBalanceService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = Supplier::query()
            ->filter($filters)
            ->when(
                ! filter_var($filters['include_zero'] ?? false, FILTER_VALIDATE_BOOLEAN),
                fn ($query) => $query->where('payable_balance', '>', 0),
            )
            ->orderByDesc('payable_balance')
            ->orderBy('name');

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }
}
