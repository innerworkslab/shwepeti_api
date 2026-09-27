<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierApBalances\SupplierApBalanceResource;
use App\Models\Supplier;
use App\Services\SupplierApBalances\SupplierApBalanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierApBalanceController extends Controller
{
    public function __construct(private readonly SupplierApBalanceService $supplierApBalanceService) {}

    public function index(Request $request): JsonResponse
    {
        $balances = $this->supplierApBalanceService->paginate($request->only([
            'search',
            'supplier_type',
            'is_active',
            'include_zero',
            'page',
            'per_page',
        ]));

        return ApiResponse::resource(
            'Supplier AP balances retrieved successfully.',
            SupplierApBalanceResource::collection($balances),
        );
    }

    public function show(Supplier $supplierApBalance): JsonResponse
    {
        return ApiResponse::resource(
            'Supplier AP balance retrieved successfully.',
            SupplierApBalanceResource::make($supplierApBalance),
        );
    }
}
