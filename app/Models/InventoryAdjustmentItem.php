<?php

namespace App\Models;

use App\Enums\AdjustmentTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['inventory_adjustment_id', 'item_id', 'unit_id', 'system_quantity', 'physical_quantity', 'adjustment_type', 'adjustment_quantity', 'base_quantity', 'batch_no', 'expiry_date', 'remark'])]
class InventoryAdjustmentItem extends Model implements AuditableContract
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:6',
            'physical_quantity' => 'decimal:6',
            'adjustment_type' => AdjustmentTypeEnum::class,
            'adjustment_quantity' => 'decimal:6',
            'base_quantity' => 'decimal:6',
            'expiry_date' => 'date',
        ];
    }

    public function inventoryAdjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
