<?php

namespace App\Models;

use App\Enums\SupplierApTransactionTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_id', 'transaction_type', 'amount', 'balance_after', 'reference_type', 'reference_id', 'transaction_date', 'remark', 'created_by'])]
class SupplierApLedger extends Model
{
    protected function casts(): array
    {
        return [
            'transaction_type' => SupplierApTransactionTypeEnum::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transaction_date' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['supplier_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('supplier_id', $id))
            ->when($filters['transaction_type'] ?? null, fn (Builder $query, string $type) => $query->where('transaction_type', $type))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '<=', $date));
    }
}
