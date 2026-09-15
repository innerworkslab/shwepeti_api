<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Units\SaveUnitRequest;
use App\Http\Requests\Units\ToggleUnitActiveRequest;
use App\Http\Resources\Units\UnitResource;
use App\Models\Unit;
use App\Services\Units\UnitService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function __construct(private readonly UnitService $unitService) {}

    public function index(Request $request): JsonResponse
    {
        $units = $this->unitService->paginate($request->only(['search', 'unit_group_id', 'is_base', 'is_active', 'page', 'per_page']));

        return ApiResponse::resource(
            'Units retrieved successfully.',
            UnitResource::collection($units),
        );
    }

    public function store(SaveUnitRequest $request): JsonResponse
    {
        $unit = $this->unitService->save($request->validated());

        return ApiResponse::resource(
            'Unit saved successfully.',
            $unit,
            status: filled($request->input('id')) ? 200 : 201,
        );
    }

    public function show(Unit $unit): JsonResponse
    {
        return ApiResponse::resource(
            'Unit retrieved successfully.',
            UnitResource::make($unit->load('unitGroup')),
        );
    }

    public function destroy(Unit $unit): JsonResponse
    {
        $this->unitService->delete($unit);

        return ApiResponse::success('Unit deleted successfully.');
    }

    public function toggleActive(ToggleUnitActiveRequest $request, Unit $unit): JsonResponse
    {
        $unit = $this->unitService->toggleActive(
            $unit,
            (bool) $request->validated('is_active'),
        );

        return ApiResponse::resource('Unit active status updated successfully.', $unit);
    }
}
