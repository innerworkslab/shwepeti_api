<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menus\SaveMenuRequest;
use App\Http\Requests\Menus\ToggleMenuActiveRequest;
use App\Http\Requests\Menus\ToggleMenuAvailableRequest;
use App\Http\Resources\Menus\MenuResource;
use App\Models\Menu;
use App\Services\Menus\MenuService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function __construct(private readonly MenuService $menuService) {}

    public function index(Request $request): JsonResponse
    {
        $menus = $this->menuService->paginate($request->only(['search', 'menu_category_id', 'is_available', 'is_active', 'page', 'per_page']));

        return ApiResponse::resource(
            'Menus retrieved successfully.',
            MenuResource::collection($menus),
        );
    }

    public function store(SaveMenuRequest $request): JsonResponse
    {
        $menu = $this->menuService->save($request->validated());

        return ApiResponse::resource(
            'Menu saved successfully.',
            $menu,
            status: filled($request->input('id')) ? 200 : 201,
        );
    }

    public function show(Menu $menu): JsonResponse
    {
        return ApiResponse::resource(
            'Menu retrieved successfully.',
            MenuResource::make($menu->load([
                'menuCategory',
                'recipes.item.itemCategory',
                'recipes.item.stockUnit',
                'recipes.unit',
            ])),
        );
    }

    public function destroy(Menu $menu): JsonResponse
    {
        $this->menuService->delete($menu);

        return ApiResponse::success('Menu deleted successfully.');
    }

    public function toggleActive(ToggleMenuActiveRequest $request, Menu $menu): JsonResponse
    {
        $menu = $this->menuService->toggleActive(
            $menu,
            (bool) $request->validated('is_active'),
        );

        return ApiResponse::resource('Menu active status updated successfully.', $menu);
    }

    public function toggleAvailable(ToggleMenuAvailableRequest $request, Menu $menu): JsonResponse
    {
        $menu = $this->menuService->toggleAvailable(
            $menu,
            (bool) $request->validated('is_available'),
        );

        return ApiResponse::resource('Menu availability updated successfully.', $menu);
    }
}
