<?php

namespace App\Models;

use App\Enums\BookingChargeTypeEnum;
use App\Enums\BookingStatusEnum;
use App\Enums\BookingTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'booking_no',
    'room_id',
    'booking_type',
    'charge_type',
    'guest_name',
    'guest_phone',
    'guest_email',
    'expected_check_in_at',
    'expected_check_out_at',
    'checked_in_at',
    'checked_out_at',
    'status',
    'guest_count',
    'room_rate',
    'session_hours',
    'session_rate',
    'subtotal',
    'discount_amount',
    'tax_amount',
    'total_amount',
    'paid_amount',
    'balance_amount',
    'note',
    'created_by',
    'updated_by',
    'checked_in_by',
    'checked_out_by',
])]
class Booking extends Model implements AuditableContract
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'expected_check_in_at' => 'datetime',
            'expected_check_out_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'booking_type' => BookingTypeEnum::class,
            'charge_type' => BookingChargeTypeEnum::class,
            'status' => BookingStatusEnum::class,
            'guest_count' => 'integer',
            'room_rate' => 'decimal:2',
            'session_hours' => 'integer',
            'session_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class);
    }

    public function foodOrders(): HasMany
    {
        return $this->hasMany(FoodOrder::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    /**
     * @param  Builder<Booking>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Booking>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('booking_no', 'like', "%{$search}%")
                        ->orWhere('guest_name', 'like', "%{$search}%")
                        ->orWhere('guest_phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['room_id'] ?? null, fn (Builder $query, int|string $roomId) => $query->where('room_id', $roomId))
            ->when($filters['booking_type'] ?? null, fn (Builder $query, string $bookingType) => $query->where('booking_type', $bookingType))
            ->when($filters['charge_type'] ?? null, fn (Builder $query, string $chargeType) => $query->where('charge_type', $chargeType))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
    }
}
