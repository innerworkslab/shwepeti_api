<?php

namespace App\Models;

use App\Enums\CashbookTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['code', 'name', 'type', 'opening_balance', 'description', 'is_active'])]
class Cashbook extends Model implements AuditableContract
{
    use Auditable;

    protected static function booted(): void
    {
        static::creating(function (Cashbook $cashbook): void {
            $cashbook->current_balance = $cashbook->opening_balance ?? 0;
        });
    }

    protected function casts(): array
    {
        return [
            'type' => CashbookTypeEnum::class,
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashbookTransaction::class);
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            }))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when(array_key_exists('is_active', $filters), fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)));
    }
}
