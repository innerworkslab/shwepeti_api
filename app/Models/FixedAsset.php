<?php

namespace App\Models;

use App\Enums\FixedAssetStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['reference_no', 'asset_code', 'asset_category_id', 'cashbook_id', 'name', 'serial_number', 'location', 'purchase_date', 'purchase_amount', 'warranty_expiry_date', 'documents', 'status', 'remark', 'created_by'])]
class FixedAsset extends Model implements AuditableContract
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'purchase_date' => 'datetime',
            'purchase_amount' => 'decimal:2',
            'warranty_expiry_date' => 'date',
            'documents' => 'array',
            'status' => FixedAssetStatusEnum::class,
        ];
    }

    public function assetCategory(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }

    public function cashbook(): BelongsTo
    {
        return $this->belongsTo(Cashbook::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cashbookTransaction(): MorphOne
    {
        return $this->morphOne(CashbookTransaction::class, 'reference');
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('asset_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            }))
            ->when($filters['asset_category_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('asset_category_id', $id))
            ->when($filters['cashbook_id'] ?? null, fn (Builder $query, mixed $id) => $query->where('cashbook_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('purchase_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('purchase_date', '<=', $date));
    }
}
