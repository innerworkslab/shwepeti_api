<?php

namespace App\Services\ItemPriceHistories;

use App\Models\Item;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ItemPriceHistoryService
{
    public function byItem(Item $item, array $filters = []): LengthAwarePaginator|Collection
    {
        $query = $item->priceHistories()
            ->with(['changer'])
            ->filter($filters)
            ->latest('changed_at');

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }
}
