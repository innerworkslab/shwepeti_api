<?php

namespace App\Services\Menus;

use App\Http\Resources\Menus\MenuResource;
use App\Models\Menu;
use App\Services\Inventory\InventoryDocumentService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MenuService
{
    public function __construct(private readonly InventoryDocumentService $inventoryDocumentService) {}

    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = Menu::query()
            ->with(['menuCategory', 'warehouse', 'recipes.item.itemCategory', 'recipes.item.stockUnit', 'recipes.unit'])
            ->filter($filters)
            ->latest();

        if (! isset($filters['page'])) {
            return $query->where('is_active', true)->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data): MenuResource
    {
        return DB::transaction(function () use ($data): MenuResource {
            $existingMenu = filled($data['id'] ?? null)
                ? Menu::query()->whereKey($data['id'])->lockForUpdate()->firstOrFail()
                : null;
            $imageUrl = $this->imageUrl($data, $existingMenu?->image_url);

            $menu = Menu::query()->updateOrCreate(['id' => $data['id'] ?? null], [
                'menu_category_id' => $data['menu_category_id'],
                'warehouse_id' => $data['warehouse_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'cost_price' => $data['cost_price'] ?? null,
                'image_url' => $imageUrl,
                'is_available' => $data['is_available'] ?? true,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (isset($data['recipes'])) {
                $this->syncRecipes($menu, $data['recipes']);
            }

            $menu = $menu->refresh()->load([
                'menuCategory',
                'warehouse',
                'recipes.item.itemCategory',
                'recipes.item.stockUnit',
                'recipes.unit',
            ]);

            return new MenuResource($menu);
        });
    }

    public function delete(Menu $menu): void
    {
        DB::transaction(fn () => $menu->delete());
    }

    public function toggleActive(Menu $menu, bool $isActive): MenuResource
    {
        return $this->updateToggle($menu, ['is_active' => $isActive]);
    }

    public function toggleAvailable(Menu $menu, bool $isAvailable): MenuResource
    {
        return $this->updateToggle($menu, ['is_available' => $isAvailable]);
    }

    /**
     * @param  array<string, bool>  $values
     */
    private function updateToggle(Menu $menu, array $values): MenuResource
    {
        return DB::transaction(function () use ($menu, $values): MenuResource {
            $menu->update($values);

            $menu = $menu->refresh()->load([
                'menuCategory',
                'warehouse',
                'recipes.item.itemCategory',
                'recipes.item.stockUnit',
                'recipes.unit',
            ]);

            return new MenuResource($menu);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function imageUrl(array $data, ?string $currentUrl): ?string
    {
        if (($data['image'] ?? null) instanceof UploadedFile) {
            $path = $data['image']->store('menus', 'public');

            return $path === false ? $currentUrl : $path;
        }

        if (array_key_exists('image_url', $data)) {
            return $data['image_url'];
        }

        return $currentUrl;
    }

    /**
     * @param  array<int, array<string, mixed>>  $recipes
     */
    private function syncRecipes(Menu $menu, array $recipes): void
    {
        $payloadKeys = collect($recipes)
            ->map(fn (array $recipe): string => $recipe['item_id'].'-'.$recipe['unit_id'])
            ->all();

        $menu->recipes()
            ->get()
            ->each(function ($recipe) use ($payloadKeys): void {
                if (! in_array($recipe->item_id.'-'.$recipe->unit_id, $payloadKeys, true)) {
                    $recipe->delete();
                }
            });

        foreach ($recipes as $recipe) {
            $menu->recipes()->updateOrCreate([
                'item_id' => $recipe['item_id'],
                'unit_id' => $recipe['unit_id'],
            ], [
                'quantity' => $recipe['quantity'],
                'base_quantity' => $this->inventoryDocumentService->baseQuantity((int) $recipe['item_id'], (int) $recipe['unit_id'], $recipe['quantity']),
                'remark' => $recipe['remark'] ?? null,
            ]);
        }
    }
}
