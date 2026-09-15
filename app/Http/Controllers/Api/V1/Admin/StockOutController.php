<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockOuts\SaveStockOutRequest;
use App\Http\Requests\StockOuts\UpdateStockOutStatusRequest;
use App\Http\Resources\StockOuts\StockOutResource;
use App\Models\StockOut;
use App\Services\StockOuts\StockOutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockOutController extends Controller
{
    public function __construct(private readonly StockOutService $stockOutService) {}

    public function index(Request $request): JsonResponse
    {
        $stockOuts = $this->stockOutService->paginate($request->only(['search', 'warehouse_id', 'status', 'date_from', 'date_to', 'page', 'per_page']));

        return ApiResponse::resource('Stock outs retrieved successfully.', StockOutResource::collection($stockOuts));
    }

    public function store(SaveStockOutRequest $request): JsonResponse
    {
        $stockOut = $this->stockOutService->save($request->validated(), $request->user());

        return ApiResponse::resource('Stock out saved successfully.', $stockOut, status: filled($request->input('id')) ? 200 : 201);
    }

    public function show(StockOut $stockOut): JsonResponse
    {
        return ApiResponse::resource('Stock out retrieved successfully.', StockOutResource::make($stockOut->load(['warehouse', 'creator', 'items.item', 'items.unit'])));
    }

    public function destroy(StockOut $stockOut): JsonResponse
    {
        $this->stockOutService->delete($stockOut);

        return ApiResponse::success('Stock out deleted successfully.');
    }

    public function status(UpdateStockOutStatusRequest $request, StockOut $stockOut): JsonResponse
    {
        $stockOut = $this->stockOutService->updateStatus($stockOut, $request->validated('status'), $request->user());

        return ApiResponse::resource('Stock out status updated successfully.', $stockOut);
    }
}
