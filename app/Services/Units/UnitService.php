<?php

namespace App\Services\Units;

use App\Http\Resources\Units\UnitResource;
use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UnitService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = Unit::query()
            ->with('unitGroup')
            ->filter($filters)
            ->latest();

        if (! isset($filters['page'])) {
            return $query->where('is_active', true)->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data): UnitResource
    {
        return DB::transaction(function () use ($data): UnitResource {
            $unit = Unit::query()->updateOrCreate(['id' => $data['id'] ?? null], [
                'unit_group_id' => $data['unit_group_id'],
                'name' => $data['name'],
                'symbol' => $data['symbol'],
                'is_base' => $data['is_base'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ]);

            return new UnitResource($unit->load('unitGroup')->refresh());
        });
    }

    public function delete(Unit $unit): void
    {
        DB::transaction(fn () => $unit->delete());
    }

    public function toggleActive(Unit $unit, bool $isActive): UnitResource
    {
        return DB::transaction(function () use ($unit, $isActive): UnitResource {
            $unit->update(['is_active' => $isActive]);

            return new UnitResource($unit->load('unitGroup')->refresh());
        });
    }
}
