<?php

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\ItemUnitConversion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryDocumentService
{
    public function referenceNo(string $prefix, string $modelClass, string $transactionDate): string
    {
        $date = Carbon::parse($transactionDate)->format('Ymd');
        $startsWith = "{$prefix}-{$date}-";
        $lastReference = $modelClass::query()
            ->where('reference_no', 'like', "{$startsWith}%")
            ->lockForUpdate()
            ->latest('id')
            ->value('reference_no');

        $nextNumber = $lastReference ? ((int) substr($lastReference, -4)) + 1 : 1;

        return $startsWith.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function batchNo(string $transactionDate): string
    {
        return 'BAT-'.Carbon::parse($transactionDate)->format('Ymd').'-'.strtoupper(substr((string) Str::uuid(), 0, 8));
    }

    public function baseQuantity(int $itemId, int $unitId, mixed $quantity): string
    {
        $item = Item::query()->findOrFail($itemId);

        if ((int) $item->stock_unit_id === $unitId) {
            return $this->decimal($quantity);
        }

        $conversion = ItemUnitConversion::query()
            ->where('item_id', $itemId)
            ->where('from_unit_id', $unitId)
            ->where('to_unit_id', $item->stock_unit_id)
            ->where('is_active', true)
            ->first();

        if ($conversion) {
            return $this->decimal((float) $quantity * (float) $conversion->conversion_factor);
        }

        $inverseConversion = ItemUnitConversion::query()
            ->where('item_id', $itemId)
            ->where('from_unit_id', $item->stock_unit_id)
            ->where('to_unit_id', $unitId)
            ->where('is_active', true)
            ->first();

        if ($inverseConversion) {
            return $this->decimal((float) $quantity / (float) $inverseConversion->conversion_factor);
        }

        throw ValidationException::withMessages([
            'items' => ["Unit conversion is missing for item {$itemId} and unit {$unitId}."],
        ]);
    }

    public function lineBatchNo(array $line, string $transactionDate, ?Model $existingLine = null): ?string
    {
        if ($existingLine && filled($existingLine->batch_no)) {
            return $existingLine->batch_no;
        }

        return $this->batchNo($transactionDate);
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }
}
