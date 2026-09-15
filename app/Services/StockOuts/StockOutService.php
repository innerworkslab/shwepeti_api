<?php

namespace App\Services\StockOuts;

use App\Enums\StockOutStatusEnum;
use App\Http\Resources\StockOuts\StockOutResource;
use App\Models\StockOut;
use App\Models\User;
use App\Services\Inventory\InventoryDocumentService;
use App\Services\InventoryLedgers\InventoryLedgerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockOutService
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService,
        private readonly InventoryLedgerService $inventoryLedgerService,
    ) {}

    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = StockOut::query()->with(['warehouse', 'creator', 'items.item', 'items.unit'])->filter($filters)->latest();

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data, ?User $actor = null): StockOutResource
    {
        return DB::transaction(function () use ($data, $actor): StockOutResource {
            if (filled($data['id'] ?? null)) {
                $existingStockOut = StockOut::query()->findOrFail($data['id']);

                if ($existingStockOut->status !== StockOutStatusEnum::Draft) {
                    throw ValidationException::withMessages([
                        'id' => ['Only draft stock out can be updated.'],
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
                $values['reference_no'] = $data['reference_no'] ?? $this->inventoryDocumentService->referenceNo('SOUT', StockOut::class, $data['transaction_date']);
                $values['status'] = StockOutStatusEnum::Draft->value;
                $values['created_by'] = $actor?->id;
            } elseif (filled($data['reference_no'] ?? null)) {
                $values['reference_no'] = $data['reference_no'];
            }

            $stockOut = StockOut::query()->updateOrCreate(['id' => $data['id'] ?? null], $values);

            $this->syncItems($stockOut, $data['items']);

            return new StockOutResource($stockOut->refresh()->load(['warehouse', 'creator', 'items.item', 'items.unit']));
        });
    }

    public function delete(StockOut $stockOut): void
    {
        DB::transaction(function () use ($stockOut): void {
            if ($stockOut->status !== StockOutStatusEnum::Draft) {
                throw ValidationException::withMessages([
                    'id' => ['Only draft stock out can be deleted.'],
                ]);
            }

            $stockOut->delete();
        });
    }

    public function updateStatus(StockOut $stockOut, string $status, ?User $actor = null): StockOutResource
    {
        return DB::transaction(function () use ($stockOut, $status, $actor): StockOutResource {
            $stockOut = StockOut::query()->whereKey($stockOut->id)->lockForUpdate()->firstOrFail();

            if ($stockOut->status !== StockOutStatusEnum::Draft) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft stock out can change status.'],
                ]);
            }

            if ($status === StockOutStatusEnum::Posted->value) {
                $this->inventoryLedgerService->postOut($stockOut, $actor);
            }

            $stockOut->update(['status' => $status]);

            return new StockOutResource($stockOut->refresh()->load(['warehouse', 'creator', 'items.item', 'items.unit']));
        });
    }

    private function syncItems(StockOut $stockOut, array $items): void
    {
        $ids = collect($items)->pluck('id')->filter()->all();
        $stockOut->items()->when($ids !== [], fn ($query) => $query->whereNotIn('id', $ids))->when($ids === [], fn ($query) => $query)->delete();

        foreach ($items as $item) {
            $existingLine = filled($item['id'] ?? null) ? $stockOut->items()->find($item['id']) : null;

            $stockOut->items()->updateOrCreate(['id' => $item['id'] ?? null], [
                'item_id' => $item['item_id'],
                'unit_id' => $item['unit_id'],
                'quantity' => $item['quantity'],
                'base_quantity' => $this->inventoryDocumentService->baseQuantity((int) $item['item_id'], (int) $item['unit_id'], $item['quantity']),
                'unit_cost' => $item['unit_cost'] ?? null,
                'total_cost' => $item['total_cost'] ?? null,
                'batch_no' => $this->inventoryDocumentService->lineBatchNo($item, (string) $stockOut->transaction_date, $existingLine),
                'remark' => $item['remark'] ?? null,
            ]);
        }
    }
}
