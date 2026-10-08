<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\ServiceOrders\ListServiceOrderRequest;
use App\Http\Requests\Pos\ServiceOrders\SaveServiceOrderRequest;
use App\Http\Requests\Pos\ServiceOrders\UpdateServiceOrderStatusRequest;
use App\Http\Resources\Pos\ServiceOrders\PosServiceOrderResource;
use App\Models\ServiceOrder;
use App\Services\ServiceOrders\ServiceOrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ServiceOrderController extends Controller
{
    public function __construct(private readonly ServiceOrderService $serviceOrderService) {}

    public function index(ListServiceOrderRequest $request): JsonResponse
    {
        return ApiResponse::resource(
            'Service orders retrieved successfully.',
            PosServiceOrderResource::collection($this->serviceOrderService->paginate($request->validated())),
        );
    }

    public function store(SaveServiceOrderRequest $request): JsonResponse
    {
        $serviceOrder = $this->serviceOrderService->create($request->validated(), $request->user());

        return ApiResponse::resource('Service order saved successfully.', $serviceOrder, status: 201);
    }

    public function show(ServiceOrder $serviceOrder): JsonResponse
    {
        return ApiResponse::resource(
            'Service order retrieved successfully.',
            PosServiceOrderResource::make($serviceOrder->load(['booking', 'room.roomCategory', 'items.item.itemCategory'])),
        );
    }

    public function status(UpdateServiceOrderStatusRequest $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $serviceOrder = $this->serviceOrderService->updateStatus($serviceOrder, $request->validated('status'), $request->user());

        return ApiResponse::resource('Service order status updated successfully.', $serviceOrder);
    }
}
