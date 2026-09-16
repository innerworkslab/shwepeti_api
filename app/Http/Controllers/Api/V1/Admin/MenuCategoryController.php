<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MenuCategories\SaveMenuCategoryRequest;
use App\Http\Requests\MenuCategories\ToggleMenuCategoryActiveRequest;
use App\Http\Resources\MenuCategories\MenuCategoryResource;
use App\Models\MenuCategory;
use App\Services\MenuCategories\MenuCategoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuCategoryController extends Controller
{
    public function __construct(private readonly MenuCategoryService $menuCategoryService) {}

    public function index(Request $request): JsonResponse
    {
        $menuCategories = $this->menuCategoryService->paginate($request->only(['search', 'is_active', 'page', 'per_page']));

        return ApiResponse::resource(
            'Menu categories retrieved successfully.',
            MenuCategoryResource::collection($menuCategories),
        );
    }

    public function store(SaveMenuCategoryRequest $request): JsonResponse
    {
        $menuCategory = $this->menuCategoryService->save($request->validated());

        return ApiResponse::resource(
            'Menu category saved successfully.',
            $menuCategory,
            status: filled($request->input('id')) ? 200 : 201,
        );
    }

    public function show(MenuCategory $menuCategory): JsonResponse
    {
        return ApiResponse::resource(
            'Menu category retrieved successfully.',
            MenuCategoryResource::make($menuCategory),
        );
    }

    public function destroy(MenuCategory $menuCategory): JsonResponse
    {
        $this->menuCategoryService->delete($menuCategory);

        return ApiResponse::success('Menu category deleted successfully.');
    }

    public function toggleActive(ToggleMenuCategoryActiveRequest $request, MenuCategory $menuCategory): JsonResponse
    {
        $menuCategory = $this->menuCategoryService->toggleActive(
            $menuCategory,
            (bool) $request->validated('is_active'),
        );

        return ApiResponse::resource('Menu category active status updated successfully.', $menuCategory);
    }
}
