<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['item_id', 'warehouse_id', 'transaction_type', 'reference_type', 'reference_id', 'quantity', 'unit_id', 'base_quantity', 'unit_cost', 'total_cost', 'batch_no', 'expiry_date', 'balance_quantity', 'transaction_date', 'remark', 'created_by'])]
class InventoryLedger extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'base_quantity' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:6',
            'balance_quantity' => 'decimal:6',
            'expiry_date' => 'date',
            'transaction_date' => 'datetime',
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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['item_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('item_id', $id))
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('warehouse_id', $id))
            ->when($filters['transaction_type'] ?? null, fn (Builder $query, string $type) => $query->where('transaction_type', $type))
            ->when($filters['reference_type'] ?? null, fn (Builder $query, string $type) => $query->where('reference_type', $type))
            ->when($filters['reference_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('reference_id', $id))
            ->when($filters['batch_no'] ?? null, fn (Builder $query, string $batchNo) => $query->where('batch_no', $batchNo))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '<=', $date));
    }
}
