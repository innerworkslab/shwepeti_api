<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rooms\SaveRoomRequest;
use App\Http\Requests\Rooms\SaveRoomStatusRequest;
use App\Http\Resources\Rooms\RoomResource;
use App\Models\Room;
use App\Services\Rooms\RoomService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function __construct(private readonly RoomService $roomService) {}

    public function index(Request $request): JsonResponse
    {
        $rooms = $this->roomService->paginate($request->only(['search', 'room_category_id', 'status', 'page', 'per_page']));

        return ApiResponse::resource(
            'Rooms retrieved successfully.',
            RoomResource::collection($rooms),
        );
    }

    public function store(SaveRoomRequest $request): JsonResponse
    {
        $room = $this->roomService->save($request->validated(), $request->user());

        return ApiResponse::resource(
            'Room saved successfully.',
            $room,
            status: filled($request->input('id')) ? 200 : 201,
        );
    }

    public function show(Room $room): JsonResponse
    {
        return ApiResponse::resource('Room retrieved successfully.', RoomResource::make($room->load(['roomCategory', 'beds'])));
    }

    public function destroy(Request $request, Room $room): JsonResponse
    {
        $this->roomService->delete($room, $request->user());

        return ApiResponse::success('Room deleted successfully.');
    }

    public function status(SaveRoomStatusRequest $request, Room $room): JsonResponse
    {
        $room = $this->roomService->updateStatus($room, $request->validated('status'), $request->user());

        return ApiResponse::resource('Room status updated successfully.', $room);
    }

    public function restore(Request $request, int $room): JsonResponse
    {
        $room = $this->roomService->restore($room, $request->user());

        return ApiResponse::resource('Room restored successfully.', $room);
    }
}
