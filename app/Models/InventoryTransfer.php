<?php

namespace App\Models;

use App\Enums\TransferStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['reference_no', 'from_warehouse_id', 'to_warehouse_id', 'transaction_date', 'status', 'remark', 'created_by'])]
class InventoryTransfer extends Model implements AuditableContract
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime',
            'status' => TransferStatusEnum::class,
        ];
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class);
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('reference_no', 'like', "%{$search}%"))
            ->when($filters['from_warehouse_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('from_warehouse_id', $id))
            ->when($filters['to_warehouse_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('to_warehouse_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '<=', $date));
    }
}
