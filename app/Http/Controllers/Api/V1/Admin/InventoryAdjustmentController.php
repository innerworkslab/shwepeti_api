<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryAdjustments\SaveInventoryAdjustmentRequest;
use App\Http\Requests\InventoryAdjustments\UpdateInventoryAdjustmentStatusRequest;
use App\Http\Resources\InventoryAdjustments\InventoryAdjustmentResource;
use App\Models\InventoryAdjustment;
use App\Services\InventoryAdjustments\InventoryAdjustmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryAdjustmentController extends Controller
{
    public function __construct(private readonly InventoryAdjustmentService $inventoryAdjustmentService) {}

    public function index(Request $request): JsonResponse
    {
        $adjustments = $this->inventoryAdjustmentService->paginate($request->only(['search', 'warehouse_id', 'status', 'date_from', 'date_to', 'page', 'per_page']));

        return ApiResponse::resource('Inventory adjustments retrieved successfully.', InventoryAdjustmentResource::collection($adjustments));
    }

    public function store(SaveInventoryAdjustmentRequest $request): JsonResponse
    {
        $adjustment = $this->inventoryAdjustmentService->save($request->validated(), $request->user());

        return ApiResponse::resource('Inventory adjustment saved successfully.', $adjustment, status: filled($request->input('id')) ? 200 : 201);
    }

    public function show(InventoryAdjustment $inventoryAdjustment): JsonResponse
    {
        return ApiResponse::resource('Inventory adjustment retrieved successfully.', InventoryAdjustmentResource::make($inventoryAdjustment->load(['warehouse', 'creator', 'items.item', 'items.unit'])));
    }

    public function destroy(InventoryAdjustment $inventoryAdjustment): JsonResponse
    {
        $this->inventoryAdjustmentService->delete($inventoryAdjustment);

        return ApiResponse::success('Inventory adjustment deleted successfully.');
    }

    public function status(UpdateInventoryAdjustmentStatusRequest $request, InventoryAdjustment $inventoryAdjustment): JsonResponse
    {
        $adjustment = $this->inventoryAdjustmentService->updateStatus($inventoryAdjustment, $request->validated('status'), $request->user());

        return ApiResponse::resource('Inventory adjustment status updated successfully.', $adjustment);
    }
}
