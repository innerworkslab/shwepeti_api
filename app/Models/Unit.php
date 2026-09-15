<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['unit_group_id', 'name', 'symbol', 'is_base', 'is_active'])]
class Unit extends Model implements AuditableContract
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function unitGroup(): BelongsTo
    {
        return $this->belongsTo(UnitGroup::class);
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(Item::class, 'stock_unit_id');
    }

    /**
     * @param  Builder<Unit>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Unit>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('symbol', 'like', "%{$search}%");
                }),
            )
            ->when(
                $filters['unit_group_id'] ?? null,
                fn (Builder $query, mixed $unitGroupId) => $query->where('unit_group_id', $unitGroupId),
            )
            ->when(
                array_key_exists('is_base', $filters),
                fn (Builder $query) => $query->where('is_base', filter_var($filters['is_base'], FILTER_VALIDATE_BOOLEAN)),
            )
            ->when(
                array_key_exists('is_active', $filters),
                fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)),
            );
    }
}
