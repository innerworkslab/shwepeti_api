<?php

namespace App\Services\ItemUnitConversions;

use App\Http\Resources\ItemUnitConversions\ItemUnitConversionResource;
use App\Models\ItemUnitConversion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ItemUnitConversionService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = ItemUnitConversion::query()
            ->with(['fromUnit', 'toUnit'])
            ->filter($filters)
            ->latest();

        if (! isset($filters['page'])) {
            return $query->where('is_active', true)->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data): ItemUnitConversionResource
    {
        return DB::transaction(function () use ($data): ItemUnitConversionResource {
            $conversion = ItemUnitConversion::query()->updateOrCreate(['id' => $data['id'] ?? null], [
                'item_id' => $data['item_id'],
                'from_unit_id' => $data['from_unit_id'],
                'to_unit_id' => $data['to_unit_id'],
                'conversion_factor' => $data['conversion_factor'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            return new ItemUnitConversionResource($conversion->refresh()->load(['fromUnit', 'toUnit']));
        });
    }

    public function delete(ItemUnitConversion $itemUnitConversion): void
    {
        DB::transaction(fn () => $itemUnitConversion->delete());
    }

    public function toggleActive(ItemUnitConversion $itemUnitConversion, bool $isActive): ItemUnitConversionResource
    {
        return DB::transaction(function () use ($itemUnitConversion, $isActive): ItemUnitConversionResource {
            $itemUnitConversion->update(['is_active' => $isActive]);

            return new ItemUnitConversionResource($itemUnitConversion->refresh()->load(['fromUnit', 'toUnit']));
        });
    }
}
