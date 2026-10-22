<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\HotelReportRequest;
use App\Services\Reports\HotelReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HotelReportController extends Controller
{
    public function __construct(private readonly HotelReportService $hotelReportService) {}

    public function index(HotelReportRequest $request, string $report): JsonResponse
    {
        $result = $this->hotelReportService->get($report, $request->validated());
        $meta = $result['pagination'];
        foreach (['report', 'date_from', 'date_to', 'currency', 'summary'] as $key) {
            if (array_key_exists($key, $result)) {
                $meta[$key] = $result[$key];
            }
        }

        return ApiResponse::success(
            'Summary Report retrieved successfully.',
            $result['rows'],
            $meta,
        );
    }
}
