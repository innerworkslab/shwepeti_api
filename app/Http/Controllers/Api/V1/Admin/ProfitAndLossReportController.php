<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ProfitAndLossReportRequest;
use App\Services\Reports\ProfitAndLossReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProfitAndLossReportController extends Controller
{
    public function __construct(private readonly ProfitAndLossReportService $profitAndLossReportService) {}

    public function index(ProfitAndLossReportRequest $request): JsonResponse
    {
        return ApiResponse::success(
            'Profit and loss report retrieved successfully.',
            $this->profitAndLossReportService->get($request->validated()),
        );
    }
}
