<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnitGroups\SaveUnitGroupRequest;
use App\Http\Requests\UnitGroups\ToggleUnitGroupActiveRequest;
use App\Http\Resources\UnitGroups\UnitGroupResource;
use App\Models\UnitGroup;
use App\Services\UnitGroups\UnitGroupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitGroupController extends Controller
{
    public function __construct(private readonly UnitGroupService $unitGroupService) {}

    public function index(Request $request): JsonResponse
    {
        $unitGroups = $this->unitGroupService->paginate($request->only(['search', 'is_active', 'page', 'per_page']));

        return ApiResponse::resource(
            'Unit groups retrieved successfully.',
            UnitGroupResource::collection($unitGroups),
        );
    }

    public function store(SaveUnitGroupRequest $request): JsonResponse
    {
        $unitGroup = $this->unitGroupService->save($request->validated());

        return ApiResponse::resource(
            'Unit group saved successfully.',
            $unitGroup,
            status: filled($request->input('id')) ? 200 : 201,
        );
    }

    public function show(UnitGroup $unitGroup): JsonResponse
    {
        return ApiResponse::resource(
            'Unit group retrieved successfully.',
            UnitGroupResource::make($unitGroup->load('units')),
        );
    }

    public function destroy(UnitGroup $unitGroup): JsonResponse
    {
        $this->unitGroupService->delete($unitGroup);

        return ApiResponse::success('Unit group deleted successfully.');
    }

    public function toggleActive(ToggleUnitGroupActiveRequest $request, UnitGroup $unitGroup): JsonResponse
    {
        $unitGroup = $this->unitGroupService->toggleActive(
            $unitGroup,
            (bool) $request->validated('is_active'),
        );

        return ApiResponse::resource('Unit group active status updated successfully.', $unitGroup);
    }
}
