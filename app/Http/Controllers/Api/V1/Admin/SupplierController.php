<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\SaveSupplierRequest;
use App\Http\Requests\Suppliers\ToggleSupplierActiveRequest;
use App\Http\Resources\Suppliers\SupplierResource;
use App\Models\Supplier;
use App\Services\Suppliers\SupplierService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(private readonly SupplierService $supplierService) {}

    public function index(Request $request): JsonResponse
    {
        $suppliers = $this->supplierService->paginate($request->only(['search', 'supplier_type', 'is_active', 'page', 'per_page']));

        return ApiResponse::resource('Suppliers retrieved successfully.', SupplierResource::collection($suppliers));
    }

    public function store(SaveSupplierRequest $request): JsonResponse
    {
        $supplier = $this->supplierService->save($request->validated());

        return ApiResponse::resource('Supplier saved successfully.', $supplier, status: filled($request->input('id')) ? 200 : 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return ApiResponse::resource('Supplier retrieved successfully.', SupplierResource::make($supplier));
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->supplierService->delete($supplier);

        return ApiResponse::success('Supplier deleted successfully.');
    }

    public function toggleActive(ToggleSupplierActiveRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier = $this->supplierService->toggleActive($supplier, (bool) $request->validated('is_active'));

        return ApiResponse::resource('Supplier active status updated successfully.', $supplier);
    }

    public function restore(int $supplier): JsonResponse
    {
        return ApiResponse::resource('Supplier restored successfully.', $this->supplierService->restore($supplier));
    }
}
