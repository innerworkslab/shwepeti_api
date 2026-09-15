<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryLedgers\InventoryLedgerResource;
use App\Models\InventoryLedger;
use App\Services\InventoryLedgers\InventoryLedgerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryLedgerController extends Controller
{
    public function __construct(private readonly InventoryLedgerService $inventoryLedgerService) {}

    public function index(Request $request): JsonResponse
    {
        $ledgers = $this->inventoryLedgerService->paginate($request->only(['item_id', 'warehouse_id', 'transaction_type', 'reference_type', 'reference_id', 'batch_no', 'date_from', 'date_to', 'page', 'per_page']));

        return ApiResponse::resource('Inventory ledgers retrieved successfully.', InventoryLedgerResource::collection($ledgers));
    }

    public function show(InventoryLedger $inventoryLedger): JsonResponse
    {
        return ApiResponse::resource('Inventory ledger retrieved successfully.', InventoryLedgerResource::make($inventoryLedger->load(['item', 'warehouse', 'unit', 'creator'])));
    }
}
