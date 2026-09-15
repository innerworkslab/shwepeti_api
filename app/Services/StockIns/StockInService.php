<?php

namespace App\Services\StockIns;

use App\Enums\StockInStatusEnum;
use App\Http\Resources\StockIns\StockInResource;
use App\Models\StockIn;
use App\Models\User;
use App\Services\Inventory\InventoryDocumentService;
use App\Services\InventoryLedgers\InventoryLedgerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockInService
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService,
        private readonly InventoryLedgerService $inventoryLedgerService,
    ) {}

    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = StockIn::query()->with(['warehouse', 'creator', 'items.item', 'items.unit'])->filter($filters)->latest();

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data, ?User $actor = null): StockInResource
    {
        return DB::transaction(function () use ($data, $actor): StockInResource {
            if (filled($data['id'] ?? null)) {
                $existingStockIn = StockIn::query()->findOrFail($data['id']);

                if ($existingStockIn->status !== StockInStatusEnum::Draft) {
                    throw ValidationException::withMessages([
                        'id' => ['Only draft stock in can be updated.'],
                    ]);
                }
            }

            $values = [
                'warehouse_id' => $data['warehouse_id'],
                'transaction_date' => $data['transaction_date'],
                'remark' => $data['remark'] ?? null,
            ];

            if (blank($data['id'] ?? null)) {
                $values['reference_no'] = $data['reference_no'] ?? $this->inventoryDocumentService->referenceNo('SIN', StockIn::class, $data['transaction_date']);
                $values['status'] = StockInStatusEnum::Draft->value;
                $values['created_by'] = $actor?->id;
            } elseif (filled($data['reference_no'] ?? null)) {
                $values['reference_no'] = $data['reference_no'];
            }

            $stockIn = StockIn::query()->updateOrCreate(['id' => $data['id'] ?? null], $values);

            $this->syncItems($stockIn, $data['items']);

            return new StockInResource($stockIn->refresh()->load(['warehouse', 'creator', 'items.item', 'items.unit']));
        });
    }

    public function delete(StockIn $stockIn): void
    {
        DB::transaction(function () use ($stockIn): void {
            if ($stockIn->status !== StockInStatusEnum::Draft) {
                throw ValidationException::withMessages([
                    'id' => ['Only draft stock in can be deleted.'],
                ]);
            }

            $stockIn->delete();
        });
    }

    public function updateStatus(StockIn $stockIn, string $status, ?User $actor = null): StockInResource
    {
        return DB::transaction(function () use ($stockIn, $status, $actor): StockInResource {
            $stockIn = StockIn::query()->whereKey($stockIn->id)->lockForUpdate()->firstOrFail();

            if ($stockIn->status !== StockInStatusEnum::Draft) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft stock in can change status.'],
                ]);
            }

            if ($status === StockInStatusEnum::Posted->value) {
                $this->inventoryLedgerService->postIn($stockIn, $actor);
            }

            $stockIn->update(['status' => $status]);

            return new StockInResource($stockIn->refresh()->load(['warehouse', 'creator', 'items.item', 'items.unit']));
        });
    }

    private function syncItems(StockIn $stockIn, array $items): void
    {
        $ids = collect($items)->pluck('id')->filter()->all();
        $stockIn->items()->when($ids !== [], fn ($query) => $query->whereNotIn('id', $ids))->when($ids === [], fn ($query) => $query)->delete();

        foreach ($items as $item) {
            $existingLine = filled($item['id'] ?? null) ? $stockIn->items()->find($item['id']) : null;

            $stockIn->items()->updateOrCreate(['id' => $item['id'] ?? null], [
                'item_id' => $item['item_id'],
                'unit_id' => $item['unit_id'],
                'quantity' => $item['quantity'],
                'base_quantity' => $this->inventoryDocumentService->baseQuantity((int) $item['item_id'], (int) $item['unit_id'], $item['quantity']),
                'unit_cost' => $item['unit_cost'] ?? null,
                'total_cost' => $item['total_cost'] ?? null,
                'batch_no' => $this->inventoryDocumentService->lineBatchNo($item, (string) $stockIn->transaction_date, $existingLine),
                'expiry_date' => $item['expiry_date'] ?? null,
                'remark' => $item['remark'] ?? null,
            ]);
        }
    }
}
