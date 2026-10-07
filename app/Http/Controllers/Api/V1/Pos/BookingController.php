<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\Bookings\CheckoutBookingRequest;
use App\Http\Requests\Pos\Bookings\ListBookingRequest;
use App\Http\Requests\Pos\Bookings\SaveBookingPaymentRequest;
use App\Http\Requests\Pos\Bookings\SaveBookingRequest;
use App\Http\Resources\Pos\Bookings\PosBookingResource;
use App\Models\Booking;
use App\Services\Bookings\BookingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService) {}

    public function index(ListBookingRequest $request): JsonResponse
    {
        $bookings = $this->bookingService->paginate($request->validated());

        return ApiResponse::resource(
            'Bookings retrieved successfully.',
            PosBookingResource::collection($bookings),
        );
    }

    public function store(SaveBookingRequest $request): JsonResponse
    {
        $booking = $this->bookingService->create($request->validated(), $request->user());

        return ApiResponse::resource('Booking saved successfully.', $booking, status: 201);
    }

    public function show(Booking $booking): JsonResponse
    {
        return ApiResponse::resource(
            'Booking retrieved successfully.',
            PosBookingResource::make($booking->load(['room.roomCategory', 'payments'])),
        );
    }

    public function checkIn(Request $request, Booking $booking): JsonResponse
    {
        $booking = $this->bookingService->checkIn($booking, $request->user());

        return ApiResponse::resource('Guest checked in successfully.', $booking);
    }

    public function checkOut(Request $request, Booking $booking): JsonResponse
    {
        $booking = $this->bookingService->checkOut($booking, $request->user());

        return ApiResponse::resource('Guest checked out successfully.', $booking);
    }

    public function checkoutWithPayment(CheckoutBookingRequest $request, Booking $booking): JsonResponse
    {
        $booking = $this->bookingService->checkoutWithPayment($booking, $request->validated(), $request->user());

        return ApiResponse::resource('Guest checked out successfully.', $booking);
    }

    public function partialPayment(SaveBookingPaymentRequest $request, Booking $booking): JsonResponse
    {
        $booking = $this->bookingService->addPartialPayment($booking, $request->validated(), $request->user());

        return ApiResponse::resource('Partial payment saved successfully.', $booking);
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $booking = $this->bookingService->cancel($booking, $request->user());

        return ApiResponse::resource('Booking cancelled successfully.', $booking);
    }
}
