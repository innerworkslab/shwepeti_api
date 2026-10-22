<?php

namespace App\Models;

use App\Enums\CashbookTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['reference_no', 'request_id', 'from_cashbook_id', 'to_cashbook_id', 'amount', 'transfer_date', 'remark', 'created_by'])]
class CashbookTransfer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transfer_date' => 'datetime'];
    }

    public function fromCashbook(): BelongsTo
    {
        return $this->belongsTo(Cashbook::class, 'from_cashbook_id');
    }

    public function toCashbook(): BelongsTo
    {
        return $this->belongsTo(Cashbook::class, 'to_cashbook_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(CashbookTransaction::class, 'reference');
    }

    public function scopeForPos(Builder $query): Builder
    {
        $types = [CashbookTypeEnum::PosCash->value, CashbookTypeEnum::PosBank->value];

        return $query->whereHas('fromCashbook', fn (Builder $query) => $query->whereIn('type', $types));
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('reference_no', 'like', "%{$search}%"))
            ->when($filters['cashbook_id'] ?? null, fn (Builder $query, mixed $id) => $query->where(fn (Builder $query) => $query->where('from_cashbook_id', $id)->orWhere('to_cashbook_id', $id)))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transfer_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transfer_date', '<=', $date));
    }
}
