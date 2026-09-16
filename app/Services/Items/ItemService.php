<?php

namespace App\Services\Items;

use App\Http\Resources\Items\ItemResource;
use App\Models\Item;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ItemService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = Item::query()
            ->with(['itemCategory', 'stockUnit', 'unitConversions.fromUnit', 'unitConversions.toUnit'])
            ->filter($filters)
            ->latest();

        if (! isset($filters['page'])) {
            return $query->where('is_active', true)->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function byWarehouse(int $warehouseId, array $filters = []): LengthAwarePaginator|Collection
    {
        $query = Item::query()
            ->with(['itemCategory', 'stockUnit', 'unitConversions.fromUnit', 'unitConversions.toUnit'])
            ->withSum([
                'stockBalances as balance_quantity' => fn ($query) => $query->where('warehouse_id', $warehouseId),
            ], 'quantity')
            ->filter($filters)
            ->latest();

        if (! isset($filters['page'])) {
            return $query->where('is_active', true)->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data, ?User $actor = null): ItemResource
    {
        return DB::transaction(function () use ($data, $actor): ItemResource {
            $existingItem = filled($data['id'] ?? null)
                ? Item::query()->whereKey($data['id'])->lockForUpdate()->firstOrFail()
                : null;

            $item = Item::query()->updateOrCreate(['id' => $data['id'] ?? null], [
                'item_category_id' => $data['item_category_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'sku' => $data['sku'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'stock_unit_id' => $data['stock_unit_id'],
                'description' => $data['description'] ?? null,
                'min_stock' => $data['min_stock'] ?? null,
                'price' => array_key_exists('price', $data) ? $data['price'] : $existingItem?->price,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (array_key_exists('price', $data)) {
                $this->recordPriceHistory($item, $existingItem?->price, $data['price'], $actor);
            }

            if (isset($data['item_unit_conversions'])) {
                $this->syncUnitConversions($item, $data['item_unit_conversions']);
            }

            $item = $item->refresh()->load([
                'itemCategory',
                'stockUnit',
                'unitConversions.fromUnit',
                'unitConversions.toUnit',
            ]);

            return new ItemResource($item);
        });
    }

    public function updatePrice(int $itemId, mixed $price, ?User $actor = null): ItemResource
    {
        return DB::transaction(function () use ($itemId, $price, $actor): ItemResource {
            $item = Item::query()->whereKey($itemId)->lockForUpdate()->firstOrFail();
            $oldPrice = $item->price;
            $newPrice = number_format((float) $price, 2, '.', '');

            if ($this->pricesAreDifferent($oldPrice, $newPrice)) {
                $item->update(['price' => $newPrice]);

                $item->priceHistories()->create([
                    'old_price' => $oldPrice === null ? null : number_format((float) $oldPrice, 2, '.', ''),
                    'new_price' => $newPrice,
                    'changed_at' => now(),
                    'changed_by' => $actor?->id,
                ]);
            }

            $item = $item->refresh()->load([
                'itemCategory',
                'stockUnit',
                'unitConversions.fromUnit',
                'unitConversions.toUnit',
            ]);

            return new ItemResource($item);
        });
    }

    private function pricesAreDifferent(mixed $oldPrice, string $newPrice): bool
    {
        $oldPrice = $oldPrice === null ? null : number_format((float) $oldPrice, 2, '.', '');

        return $oldPrice !== $newPrice;
    }

    private function recordPriceHistory(Item $item, mixed $oldPrice, mixed $newPrice, ?User $actor = null): void
    {
        if ($newPrice === null) {
            return;
        }

        $newPrice = number_format((float) $newPrice, 2, '.', '');

        if (! $this->pricesAreDifferent($oldPrice, $newPrice)) {
            return;
        }

        $item->priceHistories()->create([
            'old_price' => $oldPrice === null ? null : number_format((float) $oldPrice, 2, '.', ''),
            'new_price' => $newPrice,
            'changed_at' => now(),
            'changed_by' => $actor?->id,
        ]);
    }

    public function delete(Item $item): void
    {
        DB::transaction(fn () => $item->delete());
    }

    public function toggleActive(Item $item, bool $isActive): ItemResource
    {
        return DB::transaction(function () use ($item, $isActive): ItemResource {
            $item->update(['is_active' => $isActive]);

            $item = $item->refresh()->load([
                'itemCategory',
                'stockUnit',
                'unitConversions.fromUnit',
                'unitConversions.toUnit',
            ]);

            return new ItemResource($item);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $conversions
     */
    private function syncUnitConversions(Item $item, array $conversions): void
    {
        $payloadKeys = collect($conversions)
            ->map(fn (array $conversion): string => $conversion['from_unit_id'].'-'.$conversion['to_unit_id'])
            ->all();

        $item->unitConversions()
            ->get()
            ->each(function ($conversion) use ($payloadKeys): void {
                if (! in_array($conversion->from_unit_id.'-'.$conversion->to_unit_id, $payloadKeys, true)) {
                    $conversion->delete();
                }
            });

        foreach ($conversions as $conversion) {
            $item->unitConversions()->updateOrCreate([
                'from_unit_id' => $conversion['from_unit_id'],
                'to_unit_id' => $conversion['to_unit_id'],
            ], [
                'conversion_factor' => $conversion['conversion_factor'],
                'is_active' => $conversion['is_active'] ?? true,
            ]);
        }
    }
}
