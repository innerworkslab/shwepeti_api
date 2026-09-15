<?php

namespace App\Services\Warehouses;

use App\Http\Resources\Warehouses\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = Warehouse::query()->filter($filters)->latest();

        if (! isset($filters['page'])) {
            return $query->where('is_active', true)->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data): WarehouseResource
    {
        return DB::transaction(function () use ($data): WarehouseResource {
            $warehouse = Warehouse::query()->updateOrCreate(['id' => $data['id'] ?? null], [
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            return new WarehouseResource($warehouse->refresh());
        });
    }

    public function delete(Warehouse $warehouse): void
    {
        DB::transaction(fn () => $warehouse->delete());
    }

    public function toggleActive(Warehouse $warehouse, bool $isActive): WarehouseResource
    {
        return DB::transaction(function () use ($warehouse, $isActive): WarehouseResource {
            $warehouse->update(['is_active' => $isActive]);

            return new WarehouseResource($warehouse->refresh());
        });
    }
}
