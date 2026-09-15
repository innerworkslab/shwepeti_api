<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemUnitConversions\SaveItemUnitConversionRequest;
use App\Http\Requests\ItemUnitConversions\ToggleItemUnitConversionActiveRequest;
use App\Http\Resources\ItemUnitConversions\ItemUnitConversionResource;
use App\Models\ItemUnitConversion;
use App\Services\ItemUnitConversions\ItemUnitConversionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemUnitConversionController extends Controller
{
    public function __construct(private readonly ItemUnitConversionService $itemUnitConversionService) {}

    public function index(Request $request): JsonResponse
    {
        $conversions = $this->itemUnitConversionService->paginate($request->only(['item_id', 'from_unit_id', 'to_unit_id', 'is_active', 'page', 'per_page']));

        return ApiResponse::resource(
            'Item unit conversions retrieved successfully.',
            ItemUnitConversionResource::collection($conversions),
        );
    }

    public function store(SaveItemUnitConversionRequest $request): JsonResponse
    {
        $conversion = $this->itemUnitConversionService->save($request->validated());

        return ApiResponse::resource(
            'Item unit conversion saved successfully.',
            $conversion,
            status: filled($request->input('id')) ? 200 : 201,
        );
    }

    public function show(ItemUnitConversion $itemUnitConversion): JsonResponse
    {
        return ApiResponse::resource(
            'Item unit conversion retrieved successfully.',
            ItemUnitConversionResource::make($itemUnitConversion->load(['fromUnit', 'toUnit'])),
        );
    }

    public function destroy(ItemUnitConversion $itemUnitConversion): JsonResponse
    {
        $this->itemUnitConversionService->delete($itemUnitConversion);

        return ApiResponse::success('Item unit conversion deleted successfully.');
    }

    public function toggleActive(ToggleItemUnitConversionActiveRequest $request, ItemUnitConversion $itemUnitConversion): JsonResponse
    {
        $conversion = $this->itemUnitConversionService->toggleActive(
            $itemUnitConversion,
            (bool) $request->validated('is_active'),
        );

        return ApiResponse::resource('Item unit conversion active status updated successfully.', $conversion);
    }
}
