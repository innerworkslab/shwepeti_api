<?php

namespace App\Services\FixedAssets;

use App\Enums\CashbookTransactionTypeEnum;
use App\Enums\FixedAssetStatusEnum;
use App\Http\Resources\FixedAssets\FixedAssetResource;
use App\Models\FixedAsset;
use App\Models\User;
use App\Services\CashbookTransactions\CashbookTransactionService;
use App\Services\Inventory\InventoryDocumentService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FixedAssetService
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService,
        private readonly CashbookTransactionService $cashbookTransactionService,
    ) {}

    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = FixedAsset::query()
            ->with($this->relations())
            ->filter($filters)
            ->latest('purchase_date')
            ->latest('id');

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data, ?User $actor = null): FixedAssetResource
    {
        return DB::transaction(function () use ($data, $actor): FixedAssetResource {
            if (filled($data['id'] ?? null)) {
                $existing = FixedAsset::query()->findOrFail($data['id']);

                if ($existing->status !== FixedAssetStatusEnum::Draft) {
                    throw ValidationException::withMessages([
                        'id' => ['Only draft fixed assets can be updated.'],
                    ]);
                }
            }

            $values = [
                'asset_code' => $data['asset_code'],
                'asset_category_id' => $data['asset_category_id'],
                'cashbook_id' => $data['cashbook_id'],
                'name' => $data['name'],
                'serial_number' => $data['serial_number'] ?? null,
                'location' => $data['location'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'purchase_amount' => $data['purchase_amount'],
                'warranty_expiry_date' => $data['warranty_expiry_date'] ?? null,
                'documents' => $data['documents'] ?? null,
                'remark' => $data['remark'] ?? null,
            ];

            if (blank($data['id'] ?? null)) {
                $values['reference_no'] = $data['reference_no'] ?? $this->inventoryDocumentService->referenceNo('FAS', FixedAsset::class, $data['purchase_date']);
                $values['status'] = FixedAssetStatusEnum::Draft->value;
                $values['created_by'] = $actor?->id;
            } elseif (filled($data['reference_no'] ?? null)) {
                $values['reference_no'] = $data['reference_no'];
            }

            $fixedAsset = FixedAsset::query()->updateOrCreate(['id' => $data['id'] ?? null], $values);

            return new FixedAssetResource($fixedAsset->refresh()->load($this->relations()));
        });
    }

    public function delete(FixedAsset $fixedAsset): void
    {
        DB::transaction(function () use ($fixedAsset): void {
            if ($fixedAsset->status !== FixedAssetStatusEnum::Draft) {
                throw ValidationException::withMessages([
                    'id' => ['Only draft fixed assets can be deleted.'],
                ]);
            }

            $fixedAsset->delete();
        });
    }

    public function updateStatus(FixedAsset $fixedAsset, string $status, ?User $actor = null): FixedAssetResource
    {
        return DB::transaction(function () use ($fixedAsset, $status, $actor): FixedAssetResource {
            $fixedAsset = FixedAsset::query()->whereKey($fixedAsset->id)->lockForUpdate()->firstOrFail();

            if ($fixedAsset->status !== FixedAssetStatusEnum::Draft) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft fixed assets can change status.'],
                ]);
            }

            if ($status === FixedAssetStatusEnum::Posted->value) {
                $this->cashbookTransactionService->post([
                    'cashbook_id' => $fixedAsset->cashbook_id,
                    'transaction_type' => CashbookTransactionTypeEnum::Expense->value,
                    'amount' => $fixedAsset->purchase_amount,
                    'transaction_date' => $fixedAsset->purchase_date->format('Y-m-d H:i:s'),
                    'remark' => "Fixed asset purchase: {$fixedAsset->asset_code} - {$fixedAsset->name}",
                ], $actor, $fixedAsset);
            }

            $fixedAsset->update(['status' => $status]);

            return new FixedAssetResource($fixedAsset->refresh()->load($this->relations()));
        });
    }

    private function relations(): array
    {
        return ['assetCategory', 'cashbook', 'cashbookTransaction', 'creator'];
    }
}
