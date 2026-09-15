<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['item_id', 'warehouse_id', 'quantity', 'reserved_quantity', 'available_quantity'])]
class InventoryStockBalance extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'reserved_quantity' => 'decimal:6',
            'available_quantity' => 'decimal:6',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['item_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('item_id', $id))
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('warehouse_id', $id));
    }
}
