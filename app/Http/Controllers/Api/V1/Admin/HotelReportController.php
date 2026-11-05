<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\HotelReportRequest;
use App\Services\Reports\HotelReportExportService;
use App\Services\Reports\HotelReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HotelReportController extends Controller
{
    public function __construct(private readonly HotelReportService $hotelReportService) {}

    public function export(HotelReportRequest $request, string $report, HotelReportExportService $exporter): StreamedResponse
    {
        $result = $this->hotelReportService->get($report, $request->validated(), paginate: false);

        return response()->streamDownload(
            fn () => $exporter->write($result),
            "{$report}_{$result['date_from']}_{$result['date_to']}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store'],
        );
    }

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
