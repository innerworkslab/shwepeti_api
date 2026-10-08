<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\FoodOrders\ListFoodOrderRequest;
use App\Http\Requests\Pos\FoodOrders\SaveFoodOrderRequest;
use App\Http\Requests\Pos\FoodOrders\UpdateFoodOrderStatusRequest;
use App\Http\Resources\Pos\FoodOrders\PosFoodOrderResource;
use App\Models\FoodOrder;
use App\Services\FoodOrders\FoodOrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class FoodOrderController extends Controller
{
    public function __construct(private readonly FoodOrderService $foodOrderService) {}

    public function index(ListFoodOrderRequest $request): JsonResponse
    {
        return ApiResponse::resource(
            'Food orders retrieved successfully.',
            PosFoodOrderResource::collection($this->foodOrderService->paginate($request->validated())),
        );
    }

    public function store(SaveFoodOrderRequest $request): JsonResponse
    {
        $foodOrder = $this->foodOrderService->create($request->validated(), $request->user());

        return ApiResponse::resource('Food order saved successfully.', $foodOrder, status: 201);
    }

    public function show(FoodOrder $foodOrder): JsonResponse
    {
        return ApiResponse::resource(
            'Food order retrieved successfully.',
            PosFoodOrderResource::make($foodOrder->load(['booking', 'room.roomCategory', 'items.menu.menuCategory'])),
        );
    }

    public function status(UpdateFoodOrderStatusRequest $request, FoodOrder $foodOrder): JsonResponse
    {
        $foodOrder = $this->foodOrderService->updateStatus($foodOrder, $request->validated('status'), $request->user());

        return ApiResponse::resource('Food order status updated successfully.', $foodOrder);
    }
}
