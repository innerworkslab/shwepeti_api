<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FixedAssets\SaveFixedAssetRequest;
use App\Http\Requests\FixedAssets\UpdateFixedAssetStatusRequest;
use App\Http\Resources\FixedAssets\FixedAssetResource;
use App\Models\FixedAsset;
use App\Services\FixedAssets\FixedAssetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FixedAssetController extends Controller
{
    public function __construct(private readonly FixedAssetService $fixedAssetService) {}

    public function index(Request $request): JsonResponse
    {
        $fixedAssets = $this->fixedAssetService->paginate($request->only([
            'search', 'asset_category_id', 'cashbook_id', 'status', 'date_from', 'date_to', 'page', 'per_page',
        ]));

        return ApiResponse::resource('Fixed assets retrieved successfully.', FixedAssetResource::collection($fixedAssets));
    }

    public function store(SaveFixedAssetRequest $request): JsonResponse
    {
        $fixedAsset = $this->fixedAssetService->save($request->validated(), $request->user());

        return ApiResponse::resource('Fixed asset saved successfully.', $fixedAsset, status: filled($request->input('id')) ? 200 : 201);
    }

    public function show(FixedAsset $fixedAsset): JsonResponse
    {
        return ApiResponse::resource('Fixed asset retrieved successfully.', FixedAssetResource::make(
            $fixedAsset->load(['assetCategory', 'cashbook', 'cashbookTransaction', 'creator']),
        ));
    }

    public function destroy(FixedAsset $fixedAsset): JsonResponse
    {
        $this->fixedAssetService->delete($fixedAsset);

        return ApiResponse::success('Fixed asset deleted successfully.');
    }

    public function status(UpdateFixedAssetStatusRequest $request, FixedAsset $fixedAsset): JsonResponse
    {
        $fixedAsset = $this->fixedAssetService->updateStatus($fixedAsset, $request->validated('status'), $request->user());

        return ApiResponse::resource('Fixed asset status updated successfully.', $fixedAsset);
    }
}
