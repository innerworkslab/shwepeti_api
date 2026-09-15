<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouses\SaveWarehouseRequest;
use App\Http\Requests\Warehouses\ToggleWarehouseActiveRequest;
use App\Http\Resources\Warehouses\WarehouseResource;
use App\Models\Warehouse;
use App\Services\Warehouses\WarehouseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(private readonly WarehouseService $warehouseService) {}

    public function index(Request $request): JsonResponse
    {
        $warehouses = $this->warehouseService->paginate($request->only(['search', 'is_active', 'page', 'per_page']));

        return ApiResponse::resource('Warehouses retrieved successfully.', WarehouseResource::collection($warehouses));
    }

    public function store(SaveWarehouseRequest $request): JsonResponse
    {
        $warehouse = $this->warehouseService->save($request->validated());

        return ApiResponse::resource('Warehouse saved successfully.', $warehouse, status: filled($request->input('id')) ? 200 : 201);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        return ApiResponse::resource('Warehouse retrieved successfully.', WarehouseResource::make($warehouse));
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->warehouseService->delete($warehouse);

        return ApiResponse::success('Warehouse deleted successfully.');
    }

    public function toggleActive(ToggleWarehouseActiveRequest $request, Warehouse $warehouse): JsonResponse
    {
        $warehouse = $this->warehouseService->toggleActive($warehouse, (bool) $request->validated('is_active'));

        return ApiResponse::resource('Warehouse active status updated successfully.', $warehouse);
    }
}
