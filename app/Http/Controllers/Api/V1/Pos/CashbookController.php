<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\Cashbooks\ListCashbookRequest;
use App\Http\Resources\Cashbooks\CashbookResource;
use App\Services\Cashbooks\CashbookService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CashbookController extends Controller
{
    public function __construct(private readonly CashbookService $cashbookService) {}

    public function index(ListCashbookRequest $request): JsonResponse
    {
        $cashbooks = $this->cashbookService->paginateForPos($request->validated());

        return ApiResponse::resource('Cashbooks retrieved successfully.', CashbookResource::collection($cashbooks));
    }
}
