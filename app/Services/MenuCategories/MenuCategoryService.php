<?php

namespace App\Services\MenuCategories;

use App\Http\Resources\MenuCategories\MenuCategoryResource;
use App\Models\MenuCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MenuCategoryService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = MenuCategory::query()
            ->filter($filters)
            ->orderBy('sort_order')
            ->latest();

        if (! isset($filters['page'])) {
            return $query->where('is_active', true)->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data): MenuCategoryResource
    {
        return DB::transaction(function () use ($data): MenuCategoryResource {
            $menuCategory = MenuCategory::query()->updateOrCreate(['id' => $data['id'] ?? null], [
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'code' => $data['code'],
                'description' => $data['description'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);

            return new MenuCategoryResource($menuCategory->refresh());
        });
    }

    public function delete(MenuCategory $menuCategory): void
    {
        DB::transaction(function () use ($menuCategory): void {
            if ($menuCategory->menus()->exists()) {
                throw ValidationException::withMessages([
                    'menu_category' => ['This menu category has menus and cannot be deleted.'],
                ]);
            }

            $menuCategory->delete();
        });
    }

    public function toggleActive(MenuCategory $menuCategory, bool $isActive): MenuCategoryResource
    {
        return DB::transaction(function () use ($menuCategory, $isActive): MenuCategoryResource {
            $menuCategory->update(['is_active' => $isActive]);

            return new MenuCategoryResource($menuCategory->refresh());
        });
    }
}
