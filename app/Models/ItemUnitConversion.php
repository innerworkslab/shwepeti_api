<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['item_id', 'from_unit_id', 'to_unit_id', 'conversion_factor', 'is_active'])]
class ItemUnitConversion extends Model implements AuditableContract
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }

    /**
     * @param  Builder<ItemUnitConversion>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<ItemUnitConversion>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['item_id'] ?? null, fn (Builder $query, mixed $itemId) => $query->where('item_id', $itemId))
            ->when($filters['from_unit_id'] ?? null, fn (Builder $query, mixed $unitId) => $query->where('from_unit_id', $unitId))
            ->when($filters['to_unit_id'] ?? null, fn (Builder $query, mixed $unitId) => $query->where('to_unit_id', $unitId))
            ->when(
                array_key_exists('is_active', $filters),
                fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)),
            );
    }
}
