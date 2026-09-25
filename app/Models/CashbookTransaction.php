<?php

namespace App\Models;

use App\Enums\CashbookTransactionTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reference_no', 'cashbook_id', 'transaction_type', 'amount', 'balance_after', 'transaction_date', 'reference_type', 'reference_id', 'remark', 'created_by'])]
class CashbookTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'transaction_type' => CashbookTransactionTypeEnum::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transaction_date' => 'datetime',
        ];
    }

    public function cashbook(): BelongsTo
    {
        return $this->belongsTo(Cashbook::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('remark', 'like', "%{$search}%");
            }))
            ->when($filters['cashbook_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('cashbook_id', $id))
            ->when($filters['transaction_type'] ?? null, fn (Builder $query, string $type) => $query->where('transaction_type', $type))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '<=', $date));
    }
}
