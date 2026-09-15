<?php

namespace App\Models;

use App\Enums\RoomStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['name', 'room_category_id', 'price', 'status', 'created_by', 'updated_by', 'deleted_by'])]
class Room extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'status' => RoomStatusEnum::class,
        ];
    }

    public function roomCategory(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(RoomBed::class);
    }

    /**
     * @param  Builder<Room>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Room>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['room_category_id'] ?? null, fn (Builder $query, int|string $categoryId) => $query->where('room_category_id', $categoryId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
    }
}
