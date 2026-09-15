<?php

namespace App\Services\UnitGroups;

use App\Http\Resources\UnitGroups\UnitGroupResource;
use App\Models\UnitGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UnitGroupService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = UnitGroup::query()
            ->with('units')
            ->filter($filters)
            ->latest();

        if (! isset($filters['page'])) {
            return $query->where('is_active', true)->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data): UnitGroupResource
    {
        return DB::transaction(function () use ($data): UnitGroupResource {
            $unitGroup = UnitGroup::query()->updateOrCreate(['id' => $data['id'] ?? null], [
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'is_active' => $data['is_active'] ?? true,
            ]);

            return new UnitGroupResource($unitGroup->load('units')->refresh());
        });
    }

    public function delete(UnitGroup $unitGroup): void
    {
        DB::transaction(function () use ($unitGroup): void {
            if ($unitGroup->units()->exists()) {
                throw ValidationException::withMessages([
                    'unit_group' => ['This unit group has units and cannot be deleted.'],
                ]);
            }

            $unitGroup->delete();
        });
    }

    public function toggleActive(UnitGroup $unitGroup, bool $isActive): UnitGroupResource
    {
        return DB::transaction(function () use ($unitGroup, $isActive): UnitGroupResource {
            $unitGroup->update(['is_active' => $isActive]);

            return new UnitGroupResource($unitGroup->load('units')->refresh());
        });
    }
}
