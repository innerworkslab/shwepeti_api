<?php

namespace App\Services\Items;

use App\Http\Resources\Items\ItemResource;
use App\Models\Item;
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

    public function save(array $data): ItemResource
    {
        return DB::transaction(function () use ($data): ItemResource {
            $item = Item::query()->updateOrCreate(['id' => $data['id'] ?? null], [
                'item_category_id' => $data['item_category_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'sku' => $data['sku'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'stock_unit_id' => $data['stock_unit_id'],
                'description' => $data['description'] ?? null,
                'min_stock' => $data['min_stock'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (isset($data['item_unit_conversions'])) {
                $this->syncUnitConversions($item, $data['item_unit_conversions']);
            }

            return new ItemResource($item->refresh()->load([
                'itemCategory',
                'stockUnit',
                'unitConversions.fromUnit',
                'unitConversions.toUnit',
            ]));
        });
    }

    public function delete(Item $item): void
    {
        DB::transaction(fn () => $item->delete());
    }

    public function toggleActive(Item $item, bool $isActive): ItemResource
    {
        return DB::transaction(function () use ($item, $isActive): ItemResource {
            $item->update(['is_active' => $isActive]);

            return new ItemResource($item->refresh()->load([
                'itemCategory',
                'stockUnit',
                'unitConversions.fromUnit',
                'unitConversions.toUnit',
            ]));
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
