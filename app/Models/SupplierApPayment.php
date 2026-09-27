<?php

namespace App\Models;

use App\Enums\SupplierApPaymentStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['reference_no', 'supplier_id', 'cashbook_id', 'amount', 'payment_date', 'status', 'remark', 'created_by'])]
class SupplierApPayment extends Model implements AuditableContract
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'datetime',
            'status' => SupplierApPaymentStatusEnum::class,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function cashbook(): BelongsTo
    {
        return $this->belongsTo(Cashbook::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('reference_no', 'like', "%{$search}%"))
            ->when($filters['supplier_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('supplier_id', $id))
            ->when($filters['cashbook_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('cashbook_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('payment_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('payment_date', '<=', $date));
    }
}
