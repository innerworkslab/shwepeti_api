<?php

namespace App\Services\RoomCategories;

use App\Http\Resources\RoomCategories\RoomCategoryResource;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomCategoryService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = RoomCategory::query()
            ->filter($filters)
            ->latest();

        if (! isset($filters['page'])) {
            return $query->where('is_active', true)->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data, ?User $actor = null): RoomCategoryResource
    {
        return DB::transaction(function () use ($data, $actor): RoomCategoryResource {
            $values = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ];

            if ($actor) {
                $values[filled($data['id'] ?? null) ? 'updated_by' : 'created_by'] = $actor->id;
            }

            $roomCategory = RoomCategory::query()->updateOrCreate(['id' => $data['id'] ?? null], $values);

            return new RoomCategoryResource($roomCategory->refresh());
        });
    }

    public function delete(RoomCategory $roomCategory, ?User $actor = null): void
    {
        DB::transaction(function () use ($roomCategory, $actor): void {
            if ($roomCategory->rooms()->exists()) {
                throw ValidationException::withMessages([
                    'room_category' => ['This room category has rooms and cannot be deleted.'],
                ]);
            }

            if ($actor) {
                $roomCategory->forceFill(['deleted_by' => $actor->id])->save();
            }

            $roomCategory->delete();
        });
    }

    public function toggleActive(RoomCategory $roomCategory, bool $isActive, ?User $actor = null): RoomCategoryResource
    {
        return DB::transaction(function () use ($roomCategory, $isActive, $actor): RoomCategoryResource {
            $values = ['is_active' => $isActive];

            if ($actor) {
                $values['updated_by'] = $actor->id;
            }

            $roomCategory->update($values);

            return new RoomCategoryResource($roomCategory->refresh());
        });
    }

    public function restore(int $id, ?User $actor = null): RoomCategoryResource
    {
        return DB::transaction(function () use ($id, $actor): RoomCategoryResource {
            $roomCategory = RoomCategory::withTrashed()->findOrFail($id);
            $roomCategory->restore();

            if ($actor) {
                $roomCategory->forceFill([
                    'updated_by' => $actor->id,
                    'deleted_by' => null,
                ])->save();
            }

            return new RoomCategoryResource($roomCategory->refresh());
        });
    }
}
