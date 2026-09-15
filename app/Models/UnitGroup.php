<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'is_active'])]
class UnitGroup extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * @param  Builder<UnitGroup>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<UnitGroup>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                }),
            )
            ->when(
                array_key_exists('is_active', $filters),
                fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)),
            );
    }
}
