<?php

namespace App\Models;

use App\Enums\FoodOrderStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'booking_id',
    'room_id',
    'order_no',
    'status',
    'subtotal',
    'discount_amount',
    'tax_amount',
    'total_amount',
    'note',
    'created_by',
    'updated_by',
])]
class FoodOrder extends Model implements AuditableContract
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'status' => FoodOrderStatusEnum::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FoodOrderItem::class);
    }

    /**
     * @param  Builder<FoodOrder>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<FoodOrder>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['booking_id'] ?? null, fn (Builder $query, mixed $bookingId) => $query->where('booking_id', $bookingId))
            ->when($filters['room_id'] ?? null, fn (Builder $query, mixed $roomId) => $query->where('room_id', $roomId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
    }
}
