<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssetCategories\SaveAssetCategoryRequest;
use App\Http\Requests\AssetCategories\ToggleAssetCategoryActiveRequest;
use App\Http\Resources\AssetCategories\AssetCategoryResource;
use App\Models\AssetCategory;
use App\Services\AssetCategories\AssetCategoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetCategoryController extends Controller
{
    public function __construct(private readonly AssetCategoryService $assetCategoryService) {}

    public function index(Request $request): JsonResponse
    {
        $categories = $this->assetCategoryService->paginate($request->only(['search', 'parent_id', 'is_active', 'page', 'per_page']));

        return ApiResponse::resource('Asset categories retrieved successfully.', AssetCategoryResource::collection($categories));
    }

    public function store(SaveAssetCategoryRequest $request): JsonResponse
    {
        $category = $this->assetCategoryService->save($request->validated());

        return ApiResponse::resource('Asset category saved successfully.', $category, status: filled($request->input('id')) ? 200 : 201);
    }

    public function show(AssetCategory $assetCategory): JsonResponse
    {
        return ApiResponse::resource('Asset category retrieved successfully.', AssetCategoryResource::make($assetCategory->load(['parent', 'children'])));
    }

    public function destroy(AssetCategory $assetCategory): JsonResponse
    {
        $this->assetCategoryService->delete($assetCategory);

        return ApiResponse::success('Asset category deleted successfully.');
    }

    public function toggleActive(ToggleAssetCategoryActiveRequest $request, AssetCategory $assetCategory): JsonResponse
    {
        $category = $this->assetCategoryService->toggleActive($assetCategory, (bool) $request->validated('is_active'));

        return ApiResponse::resource('Asset category active status updated successfully.', $category);
    }
}
