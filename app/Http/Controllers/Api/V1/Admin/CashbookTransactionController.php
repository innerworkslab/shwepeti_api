<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashbookTransactions\SaveCashbookTransactionRequest;
use App\Http\Resources\CashbookTransactions\CashbookTransactionResource;
use App\Models\CashbookTransaction;
use App\Services\CashbookTransactions\CashbookTransactionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashbookTransactionController extends Controller
{
    public function __construct(private readonly CashbookTransactionService $cashbookTransactionService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'cashbook_id', 'transaction_type', 'date_from', 'date_to', 'page', 'per_page']);
        $transactions = $this->cashbookTransactionService->paginate($filters);

        return ApiResponse::resource(
            'Cashbook ledger retrieved successfully.',
            CashbookTransactionResource::collection($transactions),
            $this->cashbookTransactionService->summary($filters),
        );
    }

    public function store(SaveCashbookTransactionRequest $request): JsonResponse
    {
        $transaction = $this->cashbookTransactionService->post($request->validated(), $request->user());

        return ApiResponse::resource('Cashbook transaction posted successfully.', $transaction, status: 201);
    }

    public function show(CashbookTransaction $cashbookTransaction): JsonResponse
    {
        return ApiResponse::resource(
            'Cashbook transaction retrieved successfully.',
            CashbookTransactionResource::make($cashbookTransaction->load(['cashbook', 'creator'])),
        );
    }
}
