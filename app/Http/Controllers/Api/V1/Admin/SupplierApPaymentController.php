<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierApPayments\SaveSupplierApPaymentRequest;
use App\Http\Requests\SupplierApPayments\UpdateSupplierApPaymentStatusRequest;
use App\Http\Resources\SupplierApPayments\SupplierApPaymentResource;
use App\Models\SupplierApPayment;
use App\Services\SupplierApPayments\SupplierApPaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierApPaymentController extends Controller
{
    public function __construct(private readonly SupplierApPaymentService $supplierApPaymentService) {}

    public function index(Request $request): JsonResponse
    {
        $payments = $this->supplierApPaymentService->paginate($request->only(['search', 'supplier_id', 'cashbook_id', 'status', 'date_from', 'date_to', 'page', 'per_page']));

        return ApiResponse::resource('Supplier AP payments retrieved successfully.', SupplierApPaymentResource::collection($payments));
    }

    public function store(SaveSupplierApPaymentRequest $request): JsonResponse
    {
        $payment = $this->supplierApPaymentService->save($request->validated(), $request->user());

        return ApiResponse::resource('Supplier AP payment saved successfully.', $payment, status: filled($request->input('id')) ? 200 : 201);
    }

    public function show(SupplierApPayment $supplierApPayment): JsonResponse
    {
        return ApiResponse::resource('Supplier AP payment retrieved successfully.', SupplierApPaymentResource::make($supplierApPayment->load(['supplier', 'cashbook', 'creator'])));
    }

    public function destroy(SupplierApPayment $supplierApPayment): JsonResponse
    {
        $this->supplierApPaymentService->delete($supplierApPayment);

        return ApiResponse::success('Supplier AP payment deleted successfully.');
    }

    public function status(UpdateSupplierApPaymentStatusRequest $request, SupplierApPayment $supplierApPayment): JsonResponse
    {
        $payment = $this->supplierApPaymentService->updateStatus($supplierApPayment, $request->validated('status'), $request->user());

        return ApiResponse::resource('Supplier AP payment status updated successfully.', $payment);
    }
}
