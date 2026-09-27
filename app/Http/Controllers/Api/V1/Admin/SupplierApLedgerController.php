<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierApLedgers\SupplierApLedgerResource;
use App\Models\SupplierApLedger;
use App\Services\SupplierApLedgers\SupplierApLedgerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierApLedgerController extends Controller
{
    public function __construct(private readonly SupplierApLedgerService $supplierApLedgerService) {}

    public function index(Request $request): JsonResponse
    {
        $ledgers = $this->supplierApLedgerService->paginate($request->only(['supplier_id', 'transaction_type', 'date_from', 'date_to', 'page', 'per_page']));

        return ApiResponse::resource('Supplier AP ledger retrieved successfully.', SupplierApLedgerResource::collection($ledgers));
    }

    public function show(SupplierApLedger $supplierApLedger): JsonResponse
    {
        return ApiResponse::resource('Supplier AP ledger entry retrieved successfully.', SupplierApLedgerResource::make($supplierApLedger->load(['supplier', 'creator'])));
    }
}
