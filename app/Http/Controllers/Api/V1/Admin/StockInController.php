<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockIns\SaveStockInRequest;
use App\Http\Requests\StockIns\UpdateStockInStatusRequest;
use App\Http\Resources\StockIns\StockInResource;
use App\Models\StockIn;
use App\Services\StockIns\StockInService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockInController extends Controller
{
    public function __construct(private readonly StockInService $stockInService) {}

    public function index(Request $request): JsonResponse
    {
        $stockIns = $this->stockInService->paginate($request->only(['search', 'warehouse_id', 'status', 'date_from', 'date_to', 'page', 'per_page']));

        return ApiResponse::resource('Stock ins retrieved successfully.', StockInResource::collection($stockIns));
    }

    public function store(SaveStockInRequest $request): JsonResponse
    {
        $stockIn = $this->stockInService->save($request->validated(), $request->user());

        return ApiResponse::resource('Stock in saved successfully.', $stockIn, status: filled($request->input('id')) ? 200 : 201);
    }

    public function show(StockIn $stockIn): JsonResponse
    {
        return ApiResponse::resource('Stock in retrieved successfully.', StockInResource::make($stockIn->load(['warehouse', 'creator', 'items.item', 'items.unit'])));
    }

    public function destroy(StockIn $stockIn): JsonResponse
    {
        $this->stockInService->delete($stockIn);

        return ApiResponse::success('Stock in deleted successfully.');
    }

    public function status(UpdateStockInStatusRequest $request, StockIn $stockIn): JsonResponse
    {
        $stockIn = $this->stockInService->updateStatus($stockIn, $request->validated('status'), $request->user());

        return ApiResponse::resource('Stock in status updated successfully.', $stockIn);
    }
}
