<?php

namespace App\Services\Menus;

use App\Http\Requests\Menus\SaveMenuRequest;
use App\Models\Item;
use App\Models\MenuCategory;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryDocumentService;
use App\Support\ExcelImportWorkbook;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MenuImportService
{
    public function __construct(
        private readonly ExcelImportWorkbook $workbook,
        private readonly MenuService $menuService,
        private readonly MenuRecipeValidator $recipeValidator,
        private readonly InventoryDocumentService $inventoryDocumentService,
    ) {}

    /** @return array{imported_menus: int, imported_recipes: int} */
    public function import(UploadedFile $file): array
    {
        $rows = $this->workbook->rows($file, 'Menus',
            ['code', 'name', 'category_code', 'warehouse_code', 'price'],
            ['code', 'name', 'category_code', 'warehouse_code', 'price', 'cost_price', 'description', 'image_url', 'is_active', 'is_available', 'recipe_item_code', 'recipe_unit', 'recipe_quantity', 'recipe_remark'],
        );
        $categories = MenuCategory::query()->where('is_active', true)->get()->keyBy('code');
        $warehouses = Warehouse::query()->where('is_active', true)->get()->keyBy('code');
        $items = Item::query()->where('is_active', true)->whereIn('code', array_column($rows, 'recipe_item_code'))->get()->keyBy('code');
        $units = Unit::query()->where('is_active', true)->get()->groupBy('symbol');
        $rules = (new SaveMenuRequest)->rules();
        unset($rules['id'], $rules['image'], $rules['recipes.*.id']);
        $rules['price'][] = 'lt:10000000000000000';
        $rules['cost_price'][] = 'lt:10000000000000000';
        $rules['recipes.*.quantity'] = ['required', 'numeric', 'min:0.000001', 'lt:1000000000000'];
        $menus = [];
        $errors = [];
        $recipeCount = 0;

        foreach ($rows as $number => $row) {
            $data = array_intersect_key($row, $rules);
            $data['code'] = strtoupper(str_replace([' ', '-'], '_', $row['code'] ?? ''));
            $data['name'] = preg_replace('/\s+/', ' ', $row['name'] ?? '');
            $data['menu_category_id'] = $categories->get($row['category_code'] ?? '')?->id;
            $data['warehouse_id'] = $warehouses->get($row['warehouse_code'] ?? '')?->id;
            $data['is_active'] = $data['is_active'] ?? '1';
            $data['is_available'] = $data['is_available'] ?? '1';
            $data['recipes'] = [];

            if (filled($row['recipe_item_code'] ?? null) || filled($row['recipe_unit'] ?? null) || filled($row['recipe_quantity'] ?? null) || filled($row['recipe_remark'] ?? null)) {
                $item = $items->get($row['recipe_item_code'] ?? '');
                $unitMatches = $units->get($row['recipe_unit'] ?? '');
                $data['recipes'][] = [
                    'item_id' => $item?->id,
                    'unit_id' => $unitMatches?->count() === 1 ? $unitMatches->first()->id : null,
                    'quantity' => $row['recipe_quantity'] ?? null,
                    'remark' => $row['recipe_remark'] ?? null,
                ];
            }

            $validator = Validator::make($data, $rules);
            $validator->after($this->recipeValidator);
            if ($validator->fails()) {
                $fieldNames = [
                    'menu_category_id' => 'category_code', 'warehouse_id' => 'warehouse_code',
                    'recipes.0.item_id' => 'recipe_item_code', 'recipes.0.unit_id' => 'recipe_unit',
                    'recipes.0.quantity' => 'recipe_quantity', 'recipes.0.remark' => 'recipe_remark',
                ];
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors['rows.'.$number.'.'.($fieldNames[$field] ?? $field)] = $messages;
                }

                continue;
            }

            $recipe = $data['recipes'][0] ?? null;
            if ($recipe !== null) {
                $baseQuantity = $this->inventoryDocumentService->baseQuantity($recipe['item_id'], $recipe['unit_id'], $recipe['quantity']);
                if ((float) $baseQuantity < 0.000001 || (float) $baseQuantity >= 1000000000000) {
                    $errors["rows.{$number}.recipe_quantity"] = ['The converted stock quantity must be at least 0.000001 and less than 1000000000000.'];

                    continue;
                }
            }
            unset($data['recipes']);
            $code = $data['code'];
            if (isset($menus[$code]) && $menus[$code]['data'] !== $data) {
                $errors["rows.{$number}.code"] = ['Repeat the same menu details on every row with this code.'];

                continue;
            }
            $menus[$code] ??= ['data' => $data, 'recipes' => []];
            if ($recipe !== null) {
                $key = $recipe['item_id'].'-'.$recipe['unit_id'];
                if (isset($menus[$code]['recipes'][$key])) {
                    $errors["rows.{$number}.recipe_item_code"] = ['This item and unit combination is repeated for the same menu.'];

                    continue;
                }
                $menus[$code]['recipes'][$key] = $recipe;
                $recipeCount++;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        try {
            DB::transaction(function () use ($menus): void {
                foreach ($menus as $menu) {
                    $this->menuService->save([...$menu['data'], 'recipes' => array_values($menu['recipes'])]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['file' => 'A menu code already exists. No menus were imported.']);
        }

        return ['imported_menus' => count($menus), 'imported_recipes' => $recipeCount];
    }
}
