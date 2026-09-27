<?php

namespace App\Models;

use App\Enums\SupplierTypeEnum;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['supplier_code', 'name', 'phone_number', 'email', 'address', 'bank_account_no', 'credit_limit_amount', 'supplier_type', 'is_active'])]
class Supplier extends Model implements AuditableContract
{
    /** @use HasFactory<SupplierFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'credit_limit_amount' => 'decimal:2',
            'payable_balance' => 'decimal:2',
            'supplier_type' => SupplierTypeEnum::class,
            'is_active' => 'boolean',
        ];
    }

    public function stockIns(): HasMany
    {
        return $this->hasMany(StockIn::class);
    }

    public function apPayments(): HasMany
    {
        return $this->hasMany(SupplierApPayment::class);
    }

    public function apLedgers(): HasMany
    {
        return $this->hasMany(SupplierApLedger::class);
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('supplier_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            }))
            ->when($filters['supplier_type'] ?? null, fn (Builder $query, string $type) => $query->where('supplier_type', $type))
            ->when(array_key_exists('is_active', $filters), fn (Builder $query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)));
    }
}
