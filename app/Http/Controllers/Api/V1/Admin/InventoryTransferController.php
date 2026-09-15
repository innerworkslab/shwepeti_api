<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryTransfers\SaveInventoryTransferRequest;
use App\Http\Requests\InventoryTransfers\UpdateInventoryTransferStatusRequest;
use App\Http\Resources\InventoryTransfers\InventoryTransferResource;
use App\Models\InventoryTransfer;
use App\Services\InventoryTransfers\InventoryTransferService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryTransferController extends Controller
{
    public function __construct(private readonly InventoryTransferService $inventoryTransferService) {}

    public function index(Request $request): JsonResponse
    {
        $transfers = $this->inventoryTransferService->paginate($request->only(['search', 'from_warehouse_id', 'to_warehouse_id', 'status', 'date_from', 'date_to', 'page', 'per_page']));

        return ApiResponse::resource('Inventory transfers retrieved successfully.', InventoryTransferResource::collection($transfers));
    }

    public function store(SaveInventoryTransferRequest $request): JsonResponse
    {
        $transfer = $this->inventoryTransferService->save($request->validated(), $request->user());

        return ApiResponse::resource('Inventory transfer saved successfully.', $transfer, status: filled($request->input('id')) ? 200 : 201);
    }

    public function show(InventoryTransfer $inventoryTransfer): JsonResponse
    {
        return ApiResponse::resource('Inventory transfer retrieved successfully.', InventoryTransferResource::make($inventoryTransfer->load(['fromWarehouse', 'toWarehouse', 'creator', 'items.item', 'items.unit'])));
    }

    public function destroy(InventoryTransfer $inventoryTransfer): JsonResponse
    {
        $this->inventoryTransferService->delete($inventoryTransfer);

        return ApiResponse::success('Inventory transfer deleted successfully.');
    }

    public function status(UpdateInventoryTransferStatusRequest $request, InventoryTransfer $inventoryTransfer): JsonResponse
    {
        $transfer = $this->inventoryTransferService->updateStatus($inventoryTransfer, $request->validated('status'), $request->user());

        return ApiResponse::resource('Inventory transfer status updated successfully.', $transfer);
    }
}
