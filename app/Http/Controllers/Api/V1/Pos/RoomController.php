<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Enums\BookingStatusEnum;
use App\Enums\RoomStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\Rooms\ListRoomRequest;
use App\Http\Resources\Pos\Rooms\PosRoomResource;
use App\Models\Room;
use App\Services\Rooms\RoomService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class RoomController extends Controller
{
    public function __construct(private readonly RoomService $roomService) {}

    public function index(ListRoomRequest $request): JsonResponse
    {
        $rooms = $this->roomService->posPaginate($request->validated());

        return ApiResponse::resource(
            'Rooms retrieved successfully.',
            PosRoomResource::collection($rooms),
        );
    }

    public function available(ListRoomRequest $request): JsonResponse
    {
        $rooms = $this->roomService->posPaginate(array_merge($request->validated(), [
            'status' => RoomStatusEnum::Available->value,
        ]));

        return ApiResponse::resource(
            'Available rooms retrieved successfully.',
            PosRoomResource::collection($rooms),
        );
    }

    public function show(Room $room): JsonResponse
    {
        return ApiResponse::resource(
            'Room retrieved successfully.',
            PosRoomResource::make($room->load([
                'roomCategory',
                'bookings' => fn ($query) => $query
                    ->whereIn('status', [BookingStatusEnum::Reserved->value, BookingStatusEnum::CheckedIn->value])
                    ->latest(),
            ])),
        );
    }
}
