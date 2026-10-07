<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\Bookings\BookingDashboardRequest;
use App\Services\Bookings\BookingDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BookingDashboardController extends Controller
{
    public function __construct(private readonly BookingDashboardService $bookingDashboardService) {}

    public function index(BookingDashboardRequest $request): JsonResponse
    {
        return ApiResponse::success(
            'Booking dashboard retrieved successfully.',
            $this->bookingDashboardService->get($request->validated()),
        );
    }
}
