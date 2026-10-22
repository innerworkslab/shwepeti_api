<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cashbooks\CloseCashbookRequest;
use App\Http\Requests\Cashbooks\ListCashbookClosingRequest;
use App\Http\Requests\Pos\Cashbooks\ListCashbookRequest;
use App\Http\Resources\Cashbooks\CashbookClosingResource;
use App\Http\Resources\Cashbooks\CashbookResource;
use App\Models\Cashbook;
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

    public function close(CloseCashbookRequest $request): JsonResponse
    {
        $cashbook = Cashbook::query()->findOrFail($request->validated('cashbook_id'));

        return ApiResponse::resource('Cashbook closed successfully.', CashbookClosingResource::make(
            $this->cashbookService->close($cashbook, $request->validated(), $request->user(), true),
        ), status: 201);
    }

    public function closings(ListCashbookClosingRequest $request, Cashbook $cashbook): JsonResponse
    {
        return ApiResponse::resource('Cashbook closings retrieved successfully.', CashbookClosingResource::collection(
            $this->cashbookService->closings($cashbook, $request->validated(), true),
        ));
    }
}
