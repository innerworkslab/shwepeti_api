<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['menu_category_id', 'name', 'code', 'description', 'price', 'cost_price', 'image_url', 'is_available', 'is_active'])]
class Menu extends Model implements AuditableContract
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'is_available' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(MenuRecipe::class);
    }

    /**
     * @param  Builder<Menu>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Menu>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                }),
            )
            ->when($filters['menu_category_id'] ?? null, fn (Builder $query, mixed $categoryId) => $query->where('menu_category_id', $categoryId))
            ->when(
                array_key_exists('is_available', $filters),
                fn (Builder $query) => $query->where('is_available', filter_var($filters['is_available'], FILTER_VALIDATE_BOOLEAN)),
            )
            ->when(
                array_key_exists('is_active', $filters),
                fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)),
            );
    }
}
