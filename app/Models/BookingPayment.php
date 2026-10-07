<?php

namespace App\Models;

use App\Enums\BookingPaymentTypeEnum;
use App\Enums\PaymentMethodEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'booking_id',
    'cashbook_id',
    'payment_no',
    'payment_type',
    'payment_method',
    'amount',
    'paid_at',
    'note',
    'created_by',
])]
class BookingPayment extends Model implements AuditableContract
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'payment_type' => BookingPaymentTypeEnum::class,
            'payment_method' => PaymentMethodEnum::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function cashbook(): BelongsTo
    {
        return $this->belongsTo(Cashbook::class);
    }
}
