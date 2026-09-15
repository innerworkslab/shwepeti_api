<?php

namespace App\Services\ItemCategories;

use App\Http\Resources\ItemCategories\ItemCategoryResource;
use App\Models\ItemCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ItemCategoryService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = ItemCategory::query()
            ->with(['parent', 'children'])
            ->filter($filters)
            ->latest();

        if (! isset($filters['page'])) {
            return $query->where('is_active', true)->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data): ItemCategoryResource
    {
        return DB::transaction(function () use ($data): ItemCategoryResource {
            $itemCategory = ItemCategory::query()->updateOrCreate(['id' => $data['id'] ?? null], [
                'parent_id' => $data['parent_id'] ?? null,
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'code' => $data['code'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            return new ItemCategoryResource($itemCategory->load(['parent', 'children'])->refresh());
        });
    }

    public function delete(ItemCategory $itemCategory): void
    {
        DB::transaction(function () use ($itemCategory): void {
            if ($itemCategory->children()->exists()) {
                throw ValidationException::withMessages([
                    'item_category' => ['This item category has child categories and cannot be deleted.'],
                ]);
            }

            $itemCategory->delete();
        });
    }

    public function toggleActive(ItemCategory $itemCategory, bool $isActive): ItemCategoryResource
    {
        return DB::transaction(function () use ($itemCategory, $isActive): ItemCategoryResource {
            $itemCategory->update(['is_active' => $isActive]);

            return new ItemCategoryResource($itemCategory->load(['parent', 'children'])->refresh());
        });
    }
}
