<?php

namespace App\Services\InventoryLedgers;

use App\Models\InventoryAdjustment;
use App\Models\InventoryLedger;
use App\Models\InventoryStockBalance;
use App\Models\InventoryTransfer;
use App\Models\StockIn;
use App\Models\StockOut;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryLedgerService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = InventoryLedger::query()->with(['item', 'warehouse', 'unit', 'creator'])->filter($filters)->latest('transaction_date');

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function postIn(StockIn $stockIn, ?User $actor = null): void
    {
        DB::transaction(function () use ($stockIn, $actor): void {
            $stockIn->loadMissing('items');

            foreach ($stockIn->items as $line) {
                $balance = $this->balance($line->item_id, $stockIn->warehouse_id);
                $newQuantity = (float) $balance->quantity + (float) $line->base_quantity;

                $balance->update([
                    'quantity' => $this->decimal($newQuantity),
                    'available_quantity' => $this->decimal($newQuantity - (float) $balance->reserved_quantity),
                ]);

                InventoryLedger::query()->create([
                    'item_id' => $line->item_id,
                    'warehouse_id' => $stockIn->warehouse_id,
                    'transaction_type' => 'in',
                    'reference_type' => $stockIn->getMorphClass(),
                    'reference_id' => $stockIn->id,
                    'quantity' => $line->quantity,
                    'unit_id' => $line->unit_id,
                    'base_quantity' => $line->base_quantity,
                    'unit_cost' => $line->unit_cost,
                    'total_cost' => $line->total_cost,
                    'batch_no' => $line->batch_no,
                    'expiry_date' => $line->expiry_date,
                    'balance_quantity' => $this->decimal($newQuantity),
                    'transaction_date' => $stockIn->transaction_date,
                    'remark' => $line->remark ?? $stockIn->remark,
                    'created_by' => $actor?->id,
                ]);
            }
        });
    }

    public function postOut(StockOut $stockOut, ?User $actor = null): void
    {
        DB::transaction(function () use ($stockOut, $actor): void {
            $stockOut->loadMissing('items');

            foreach ($stockOut->items as $line) {
                $balance = $this->balance($line->item_id, $stockOut->warehouse_id);
                $newQuantity = (float) $balance->quantity - (float) $line->base_quantity;

                if ($newQuantity < 0) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for item {$line->item_id} in warehouse {$stockOut->warehouse_id}."],
                    ]);
                }

                $balance->update([
                    'quantity' => $this->decimal($newQuantity),
                    'available_quantity' => $this->decimal($newQuantity - (float) $balance->reserved_quantity),
                ]);

                InventoryLedger::query()->create([
                    'item_id' => $line->item_id,
                    'warehouse_id' => $stockOut->warehouse_id,
                    'transaction_type' => 'out',
                    'reference_type' => $stockOut->getMorphClass(),
                    'reference_id' => $stockOut->id,
                    'quantity' => $line->quantity,
                    'unit_id' => $line->unit_id,
                    'base_quantity' => $this->decimal(-1 * (float) $line->base_quantity),
                    'unit_cost' => $line->unit_cost,
                    'total_cost' => $line->total_cost,
                    'batch_no' => $line->batch_no,
                    'expiry_date' => null,
                    'balance_quantity' => $this->decimal($newQuantity),
                    'transaction_date' => $stockOut->transaction_date,
                    'remark' => $line->remark ?? $stockOut->remark,
                    'created_by' => $actor?->id,
                ]);
            }
        });
    }

    public function postTransfer(InventoryTransfer $transfer, ?User $actor = null): void
    {
        DB::transaction(function () use ($transfer, $actor): void {
            $transfer->loadMissing('items');

            foreach ($transfer->items as $line) {
                $fromBalance = $this->balance($line->item_id, $transfer->from_warehouse_id);
                $fromQuantity = (float) $fromBalance->quantity - (float) $line->base_quantity;

                if ($fromQuantity < 0) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for item {$line->item_id} in warehouse {$transfer->from_warehouse_id}."],
                    ]);
                }

                $fromBalance->update([
                    'quantity' => $this->decimal($fromQuantity),
                    'available_quantity' => $this->decimal($fromQuantity - (float) $fromBalance->reserved_quantity),
                ]);

                InventoryLedger::query()->create([
                    'item_id' => $line->item_id,
                    'warehouse_id' => $transfer->from_warehouse_id,
                    'transaction_type' => 'transfer_out',
                    'reference_type' => $transfer->getMorphClass(),
                    'reference_id' => $transfer->id,
                    'quantity' => $line->quantity,
                    'unit_id' => $line->unit_id,
                    'base_quantity' => $this->decimal(-1 * (float) $line->base_quantity),
                    'unit_cost' => null,
                    'total_cost' => null,
                    'batch_no' => $line->batch_no,
                    'expiry_date' => $line->expiry_date,
                    'balance_quantity' => $this->decimal($fromQuantity),
                    'transaction_date' => $transfer->transaction_date,
                    'remark' => $line->remark ?? $transfer->remark,
                    'created_by' => $actor?->id,
                ]);

                $toBalance = $this->balance($line->item_id, $transfer->to_warehouse_id);
                $toQuantity = (float) $toBalance->quantity + (float) $line->base_quantity;

                $toBalance->update([
                    'quantity' => $this->decimal($toQuantity),
                    'available_quantity' => $this->decimal($toQuantity - (float) $toBalance->reserved_quantity),
                ]);

                InventoryLedger::query()->create([
                    'item_id' => $line->item_id,
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'transaction_type' => 'transfer_in',
                    'reference_type' => $transfer->getMorphClass(),
                    'reference_id' => $transfer->id,
                    'quantity' => $line->quantity,
                    'unit_id' => $line->unit_id,
                    'base_quantity' => $line->base_quantity,
                    'unit_cost' => null,
                    'total_cost' => null,
                    'batch_no' => $line->batch_no,
                    'expiry_date' => $line->expiry_date,
                    'balance_quantity' => $this->decimal($toQuantity),
                    'transaction_date' => $transfer->transaction_date,
                    'remark' => $line->remark ?? $transfer->remark,
                    'created_by' => $actor?->id,
                ]);
            }
        });
    }

    public function postAdjustment(InventoryAdjustment $adjustment, ?User $actor = null): void
    {
        DB::transaction(function () use ($adjustment, $actor): void {
            $adjustment->loadMissing('items');

            foreach ($adjustment->items as $line) {
                $balance = $this->balance($line->item_id, $adjustment->warehouse_id);
                $baseQuantity = (float) $line->base_quantity;
                $newQuantity = (float) $balance->quantity + $baseQuantity;

                if ($newQuantity < 0) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for item {$line->item_id} in warehouse {$adjustment->warehouse_id}."],
                    ]);
                }

                $balance->update([
                    'quantity' => $this->decimal($newQuantity),
                    'available_quantity' => $this->decimal($newQuantity - (float) $balance->reserved_quantity),
                ]);

                InventoryLedger::query()->create([
                    'item_id' => $line->item_id,
                    'warehouse_id' => $adjustment->warehouse_id,
                    'transaction_type' => 'adjustment_'.$line->adjustment_type->value,
                    'reference_type' => $adjustment->getMorphClass(),
                    'reference_id' => $adjustment->id,
                    'quantity' => $line->adjustment_quantity,
                    'unit_id' => $line->unit_id,
                    'base_quantity' => $line->base_quantity,
                    'unit_cost' => null,
                    'total_cost' => null,
                    'batch_no' => $line->batch_no,
                    'expiry_date' => $line->expiry_date,
                    'balance_quantity' => $this->decimal($newQuantity),
                    'transaction_date' => $adjustment->transaction_date,
                    'remark' => $line->remark ?? $adjustment->remark,
                    'created_by' => $actor?->id,
                ]);
            }
        });
    }

    private function balance(int $itemId, int $warehouseId): InventoryStockBalance
    {
        InventoryStockBalance::query()->firstOrCreate([
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
        ], [
            'quantity' => 0,
            'reserved_quantity' => 0,
            'available_quantity' => 0,
        ]);

        return InventoryStockBalance::query()
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function decimal(float $value): string
    {
        return number_format($value, 6, '.', '');
    }
}
