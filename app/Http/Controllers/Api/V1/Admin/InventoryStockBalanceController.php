<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryStockBalances\InventoryStockBalanceResource;
use App\Models\InventoryStockBalance;
use App\Services\InventoryStockBalances\InventoryStockBalanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryStockBalanceController extends Controller
{
    public function __construct(private readonly InventoryStockBalanceService $inventoryStockBalanceService) {}

    public function index(Request $request): JsonResponse
    {
        $balances = $this->inventoryStockBalanceService->paginate($request->only(['item_id', 'warehouse_id', 'page', 'per_page']));

        return ApiResponse::resource('Inventory stock balances retrieved successfully.', InventoryStockBalanceResource::collection($balances));
    }

    public function show(InventoryStockBalance $inventoryStockBalance): JsonResponse
    {
        return ApiResponse::resource('Inventory stock balance retrieved successfully.', InventoryStockBalanceResource::make($inventoryStockBalance->load(['item.stockUnit', 'warehouse'])));
    }
}
