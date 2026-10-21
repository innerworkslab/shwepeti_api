<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\CashbookTransactions\ListCashbookTransactionRequest;
use App\Http\Resources\CashbookTransactions\CashbookTransactionResource;
use App\Services\CashbookTransactions\CashbookTransactionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CashbookTransactionController extends Controller
{
    public function __construct(private readonly CashbookTransactionService $cashbookTransactionService) {}

    public function index(ListCashbookTransactionRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $transactions = $this->cashbookTransactionService->paginateForPos($filters);

        return ApiResponse::resource(
            'Cashbook ledger retrieved successfully.',
            CashbookTransactionResource::collection($transactions),
            extra: $this->cashbookTransactionService->summary($filters),
        );
    }
}
