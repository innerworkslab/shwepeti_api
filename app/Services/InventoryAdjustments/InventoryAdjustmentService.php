<?php

namespace App\Services\InventoryAdjustments;

use App\Enums\AdjustmentStatusEnum;
use App\Enums\AdjustmentTypeEnum;
use App\Http\Resources\InventoryAdjustments\InventoryAdjustmentResource;
use App\Models\InventoryAdjustment;
use App\Models\User;
use App\Services\Inventory\InventoryDocumentService;
use App\Services\InventoryLedgers\InventoryLedgerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentService
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService,
        private readonly InventoryLedgerService $inventoryLedgerService,
    ) {}

    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = InventoryAdjustment::query()->with(['warehouse', 'creator', 'items.item', 'items.unit'])->filter($filters)->latest();

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data, ?User $actor = null): InventoryAdjustmentResource
    {
        return DB::transaction(function () use ($data, $actor): InventoryAdjustmentResource {
            if (filled($data['id'] ?? null)) {
                $existingAdjustment = InventoryAdjustment::query()->findOrFail($data['id']);

                if ($existingAdjustment->status !== AdjustmentStatusEnum::Draft) {
                    throw ValidationException::withMessages([
                        'id' => ['Only draft inventory adjustment can be updated.'],
                    ]);
                }
            }

            $values = [
                'warehouse_id' => $data['warehouse_id'],
                'transaction_date' => $data['transaction_date'],
                'reason' => $data['reason'] ?? null,
                'remark' => $data['remark'] ?? null,
            ];

            if (blank($data['id'] ?? null)) {
                $values['reference_no'] = $data['reference_no'] ?? $this->inventoryDocumentService->referenceNo('ADJ', InventoryAdjustment::class, $data['transaction_date']);
                $values['status'] = AdjustmentStatusEnum::Draft->value;
                $values['created_by'] = $actor?->id;
            } elseif (filled($data['reference_no'] ?? null)) {
                $values['reference_no'] = $data['reference_no'];
            }

            $adjustment = InventoryAdjustment::query()->updateOrCreate(['id' => $data['id'] ?? null], $values);

            $this->syncItems($adjustment, $data['items']);
            return new InventoryAdjustmentResource($adjustment->refresh()->load(['warehouse', 'creator', 'items.item', 'items.unit']));
        });
    }

    public function delete(InventoryAdjustment $inventoryAdjustment): void
    {
        DB::transaction(function () use ($inventoryAdjustment): void {
            if ($inventoryAdjustment->status !== AdjustmentStatusEnum::Draft) {
                throw ValidationException::withMessages([
                    'id' => ['Only draft inventory adjustment can be deleted.'],
                ]);
            }

            $inventoryAdjustment->delete();
        });
    }

    public function updateStatus(InventoryAdjustment $inventoryAdjustment, string $status, ?User $actor = null): InventoryAdjustmentResource
    {
        return DB::transaction(function () use ($inventoryAdjustment, $status, $actor): InventoryAdjustmentResource {
            $inventoryAdjustment = InventoryAdjustment::query()->whereKey($inventoryAdjustment->id)->lockForUpdate()->firstOrFail();

            if ($inventoryAdjustment->status !== AdjustmentStatusEnum::Draft) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft inventory adjustment can change status.'],
                ]);
            }

            if ($status === AdjustmentStatusEnum::Posted->value) {
                $this->inventoryLedgerService->postAdjustment($inventoryAdjustment, $actor);
            }

            $inventoryAdjustment->update(['status' => $status]);

            return new InventoryAdjustmentResource($inventoryAdjustment->refresh()->load(['warehouse', 'creator', 'items.item', 'items.unit']));
        });
    }

    private function syncItems(InventoryAdjustment $adjustment, array $items): void
    {
        $ids = collect($items)->pluck('id')->filter()->all();
        $adjustment->items()->when($ids !== [], fn ($query) => $query->whereNotIn('id', $ids))->when($ids === [], fn ($query) => $query)->delete();

        foreach ($items as $item) {
            $existingLine = filled($item['id'] ?? null) ? $adjustment->items()->find($item['id']) : null;
            $difference = (float) $item['physical_quantity'] - (float) $item['system_quantity'];

            if ($difference === 0.0) {
                throw ValidationException::withMessages([
                    'items' => ["Physical quantity must be different from system quantity for item {$item['item_id']}."],
                ]);
            }

            $adjustmentType = $difference > 0 ? AdjustmentTypeEnum::Increase : AdjustmentTypeEnum::Decrease;
            $adjustmentQuantity = abs($difference);
            $baseQuantity = (float) $this->inventoryDocumentService->baseQuantity((int) $item['item_id'], (int) $item['unit_id'], $adjustmentQuantity);

            if ($adjustmentType === AdjustmentTypeEnum::Decrease) {
                $baseQuantity *= -1;
            }

            $adjustment->items()->updateOrCreate(['id' => $item['id'] ?? null], [
                'item_id' => $item['item_id'],
                'unit_id' => $item['unit_id'],
                'system_quantity' => $item['system_quantity'],
                'physical_quantity' => $item['physical_quantity'],
                'adjustment_type' => $adjustmentType->value,
                'adjustment_quantity' => number_format($adjustmentQuantity, 6, '.', ''),
                'base_quantity' => number_format($baseQuantity, 6, '.', ''),
                'batch_no' => $this->inventoryDocumentService->lineBatchNo($item, (string) $adjustment->transaction_date, $existingLine),
                'expiry_date' => $item['expiry_date'] ?? null,
                'remark' => $item['remark'] ?? null,
            ]);
        }
    }
}
