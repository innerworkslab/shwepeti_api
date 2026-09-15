<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'name', 'slug', 'code', 'description', 'is_active'])]
class ItemCategory extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ItemCategory::class, 'parent_id');
    }

    /**
     * @param  Builder<ItemCategory>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<ItemCategory>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                }),
            )
            ->when(
                array_key_exists('parent_id', $filters),
                fn (Builder $query) => blank($filters['parent_id'])
                    ? $query->whereNull('parent_id')
                    : $query->where('parent_id', $filters['parent_id']),
            )
            ->when(
                array_key_exists('is_active', $filters),
                fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)),
            );
    }
}
