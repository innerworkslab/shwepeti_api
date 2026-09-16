<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['item_category_id', 'name', 'code', 'sku', 'barcode', 'stock_unit_id', 'description', 'min_stock', 'is_active'])]
class Item extends Model implements AuditableContract
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'min_stock' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function itemCategory(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class);
    }

    public function stockUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'stock_unit_id');
    }

    public function unitConversions(): HasMany
    {
        return $this->hasMany(ItemUnitConversion::class);
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(InventoryStockBalance::class);
    }

    /**
     * @param  Builder<Item>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Item>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                }),
            )
            ->when($filters['item_category_id'] ?? null, fn (Builder $query, mixed $categoryId) => $query->where('item_category_id', $categoryId))
            ->when($filters['stock_unit_id'] ?? null, fn (Builder $query, mixed $unitId) => $query->where('stock_unit_id', $unitId))
            ->when(
                array_key_exists('is_active', $filters),
                fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)),
            );
    }
}
