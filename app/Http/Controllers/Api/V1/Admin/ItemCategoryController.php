<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemCategories\SaveItemCategoryRequest;
use App\Http\Requests\ItemCategories\ToggleItemCategoryActiveRequest;
use App\Http\Resources\ItemCategories\ItemCategoryResource;
use App\Models\ItemCategory;
use App\Services\ItemCategories\ItemCategoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemCategoryController extends Controller
{
    public function __construct(private readonly ItemCategoryService $itemCategoryService) {}

    public function index(Request $request): JsonResponse
    {
        $itemCategories = $this->itemCategoryService->paginate($request->only(['search', 'parent_id', 'is_active', 'page', 'per_page']));

        return ApiResponse::resource(
            'Item categories retrieved successfully.',
            ItemCategoryResource::collection($itemCategories),
        );
    }

    public function store(SaveItemCategoryRequest $request): JsonResponse
    {
        $itemCategory = $this->itemCategoryService->save($request->validated());

        return ApiResponse::resource(
            'Item category saved successfully.',
            $itemCategory,
            status: filled($request->input('id')) ? 200 : 201,
        );
    }

    public function show(ItemCategory $itemCategory): JsonResponse
    {
        return ApiResponse::resource(
            'Item category retrieved successfully.',
            ItemCategoryResource::make($itemCategory->load(['parent', 'children'])),
        );
    }

    public function destroy(ItemCategory $itemCategory): JsonResponse
    {
        $this->itemCategoryService->delete($itemCategory);

        return ApiResponse::success('Item category deleted successfully.');
    }

    public function toggleActive(ToggleItemCategoryActiveRequest $request, ItemCategory $itemCategory): JsonResponse
    {
        $itemCategory = $this->itemCategoryService->toggleActive(
            $itemCategory,
            (bool) $request->validated('is_active'),
        );

        return ApiResponse::resource('Item category active status updated successfully.', $itemCategory);
    }
}
