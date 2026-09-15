<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Items\SaveItemRequest;
use App\Http\Requests\Items\ToggleItemActiveRequest;
use App\Http\Resources\Items\ItemResource;
use App\Models\Item;
use App\Services\Items\ItemService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function __construct(private readonly ItemService $itemService) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->itemService->paginate($request->only(['search', 'item_category_id', 'stock_unit_id', 'is_active', 'page', 'per_page']));

        return ApiResponse::resource(
            'Items retrieved successfully.',
            ItemResource::collection($items),
        );
    }

    public function store(SaveItemRequest $request): JsonResponse
    {
        $item = $this->itemService->save($request->validated());

        return ApiResponse::resource(
            'Item saved successfully.',
            $item,
            status: filled($request->input('id')) ? 200 : 201,
        );
    }

    public function show(Item $item): JsonResponse
    {
        return ApiResponse::resource(
            'Item retrieved successfully.',
            ItemResource::make($item->load([
                'itemCategory',
                'stockUnit',
                'unitConversions.fromUnit',
                'unitConversions.toUnit',
            ])),
        );
    }

    public function destroy(Item $item): JsonResponse
    {
        $this->itemService->delete($item);

        return ApiResponse::success('Item deleted successfully.');
    }

    public function toggleActive(ToggleItemActiveRequest $request, Item $item): JsonResponse
    {
        $item = $this->itemService->toggleActive(
            $item,
            (bool) $request->validated('is_active'),
        );

        return ApiResponse::resource('Item active status updated successfully.', $item);
    }
}
