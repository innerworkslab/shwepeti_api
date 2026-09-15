<?php

namespace App\Services\InventoryTransfers;

use App\Enums\TransferStatusEnum;
use App\Http\Resources\InventoryTransfers\InventoryTransferResource;
use App\Models\InventoryTransfer;
use App\Models\User;
use App\Services\Inventory\InventoryDocumentService;
use App\Services\InventoryLedgers\InventoryLedgerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryTransferService
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService,
        private readonly InventoryLedgerService $inventoryLedgerService,
    ) {}

    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = InventoryTransfer::query()->with(['fromWarehouse', 'toWarehouse', 'creator', 'items.item', 'items.unit'])->filter($filters)->latest();

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data, ?User $actor = null): InventoryTransferResource
    {
        return DB::transaction(function () use ($data, $actor): InventoryTransferResource {
            if (filled($data['id'] ?? null)) {
                $existingTransfer = InventoryTransfer::query()->findOrFail($data['id']);

                if ($existingTransfer->status !== TransferStatusEnum::Draft) {
                    throw ValidationException::withMessages([
                        'id' => ['Only draft inventory transfer can be updated.'],
                    ]);
                }
            }

            $values = [
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'transaction_date' => $data['transaction_date'],
                'remark' => $data['remark'] ?? null,
            ];

            if (blank($data['id'] ?? null)) {
                $values['reference_no'] = $data['reference_no'] ?? $this->inventoryDocumentService->referenceNo('TRF', InventoryTransfer::class, $data['transaction_date']);
                $values['status'] = TransferStatusEnum::Draft->value;
                $values['created_by'] = $actor?->id;
            } elseif (filled($data['reference_no'] ?? null)) {
                $values['reference_no'] = $data['reference_no'];
            }

            $transfer = InventoryTransfer::query()->updateOrCreate(['id' => $data['id'] ?? null], $values);

            $this->syncItems($transfer, $data['items']);

            return new InventoryTransferResource($transfer->refresh()->load(['fromWarehouse', 'toWarehouse', 'creator', 'items.item', 'items.unit']));
        });
    }

    public function delete(InventoryTransfer $inventoryTransfer): void
    {
        DB::transaction(function () use ($inventoryTransfer): void {
            if ($inventoryTransfer->status !== TransferStatusEnum::Draft) {
                throw ValidationException::withMessages([
                    'id' => ['Only draft inventory transfer can be deleted.'],
                ]);
            }

            $inventoryTransfer->delete();
        });
    }

    public function updateStatus(InventoryTransfer $inventoryTransfer, string $status, ?User $actor = null): InventoryTransferResource
    {
        return DB::transaction(function () use ($inventoryTransfer, $status, $actor): InventoryTransferResource {
            $inventoryTransfer = InventoryTransfer::query()->whereKey($inventoryTransfer->id)->lockForUpdate()->firstOrFail();

            if ($inventoryTransfer->status !== TransferStatusEnum::Draft) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft inventory transfer can change status.'],
                ]);
            }

            if ($status === TransferStatusEnum::Posted->value) {
                $this->inventoryLedgerService->postTransfer($inventoryTransfer, $actor);
            }

            $inventoryTransfer->update(['status' => $status]);

            return new InventoryTransferResource($inventoryTransfer->refresh()->load(['fromWarehouse', 'toWarehouse', 'creator', 'items.item', 'items.unit']));
        });
    }

    private function syncItems(InventoryTransfer $transfer, array $items): void
    {
        $ids = collect($items)->pluck('id')->filter()->all();
        $transfer->items()->when($ids !== [], fn ($query) => $query->whereNotIn('id', $ids))->when($ids === [], fn ($query) => $query)->delete();

        foreach ($items as $item) {
            $existingLine = filled($item['id'] ?? null) ? $transfer->items()->find($item['id']) : null;

            $transfer->items()->updateOrCreate(['id' => $item['id'] ?? null], [
                'item_id' => $item['item_id'],
                'unit_id' => $item['unit_id'],
                'quantity' => $item['quantity'],
                'base_quantity' => $this->inventoryDocumentService->baseQuantity((int) $item['item_id'], (int) $item['unit_id'], $item['quantity']),
                'batch_no' => $this->inventoryDocumentService->lineBatchNo($item, (string) $transfer->transaction_date, $existingLine),
                'expiry_date' => $item['expiry_date'] ?? null,
                'remark' => $item['remark'] ?? null,
            ]);
        }
    }
}
