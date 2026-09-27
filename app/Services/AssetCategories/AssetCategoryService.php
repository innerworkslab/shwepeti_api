<?php

namespace App\Services\AssetCategories;

use App\Http\Resources\AssetCategories\AssetCategoryResource;
use App\Models\AssetCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssetCategoryService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = AssetCategory::query()
            ->with(['parent', 'children'])
            ->filter($filters)
            ->latest();

        if (! isset($filters['page'])) {
            return $query->where('is_active', true)->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data): AssetCategoryResource
    {
        return DB::transaction(function () use ($data): AssetCategoryResource {
            $category = AssetCategory::query()->updateOrCreate(['id' => $data['id'] ?? null], [
                'parent_id' => $data['parent_id'] ?? null,
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'code' => $data['code'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            return new AssetCategoryResource($category->refresh()->load(['parent', 'children']));
        });
    }

    public function delete(AssetCategory $category): void
    {
        DB::transaction(function () use ($category): void {
            if ($category->children()->exists()) {
                throw ValidationException::withMessages([
                    'asset_category' => ['This asset category has child categories and cannot be deleted.'],
                ]);
            }

            if ($category->fixedAssets()->exists()) {
                throw ValidationException::withMessages([
                    'asset_category' => ['This asset category is assigned to fixed assets and cannot be deleted.'],
                ]);
            }

            $category->delete();
        });
    }

    public function toggleActive(AssetCategory $category, bool $isActive): AssetCategoryResource
    {
        return DB::transaction(function () use ($category, $isActive): AssetCategoryResource {
            $category->update(['is_active' => $isActive]);

            return new AssetCategoryResource($category->refresh()->load(['parent', 'children']));
        });
    }
}
