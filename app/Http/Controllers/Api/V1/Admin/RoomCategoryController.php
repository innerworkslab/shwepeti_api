<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoomCategories\SaveRoomCategoryRequest;
use App\Http\Requests\RoomCategories\ToggleRoomCategoryActiveRequest;
use App\Http\Resources\RoomCategories\RoomCategoryResource;
use App\Models\RoomCategory;
use App\Services\RoomCategories\RoomCategoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomCategoryController extends Controller
{
    public function __construct(private readonly RoomCategoryService $roomCategoryService) {}

    public function index(Request $request): JsonResponse
    {
        $roomCategories = $this->roomCategoryService->paginate($request->only(['search', 'is_active', 'page', 'per_page']));

        return ApiResponse::resource(
            'Room categories retrieved successfully.',
            RoomCategoryResource::collection($roomCategories),
        );
    }

    public function store(SaveRoomCategoryRequest $request): JsonResponse
    {
        $roomCategory = $this->roomCategoryService->save($request->validated(), $request->user());

        return ApiResponse::resource(
            'Room category saved successfully.',
            $roomCategory,
            status: filled($request->input('id')) ? 200 : 201,
        );
    }

    public function show(RoomCategory $roomCategory): JsonResponse
    {
        return ApiResponse::resource('Room category retrieved successfully.', RoomCategoryResource::make($roomCategory));
    }

    public function destroy(Request $request, RoomCategory $roomCategory): JsonResponse
    {
        $this->roomCategoryService->delete($roomCategory, $request->user());

        return ApiResponse::success('Room category deleted successfully.');
    }

    public function toggleActive(ToggleRoomCategoryActiveRequest $request, RoomCategory $roomCategory): JsonResponse
    {
        $roomCategory = $this->roomCategoryService->toggleActive(
            $roomCategory,
            (bool) $request->validated('is_active'),
            $request->user(),
        );

        return ApiResponse::resource('Room category active status updated successfully.', $roomCategory);
    }

    public function restore(Request $request, int $roomCategory): JsonResponse
    {
        $roomCategory = $this->roomCategoryService->restore($roomCategory, $request->user());

        return ApiResponse::resource('Room category restored successfully.', $roomCategory);
    }
}
