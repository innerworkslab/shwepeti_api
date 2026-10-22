<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashbookTransfers\ListCashbookTransferRequest;
use App\Http\Requests\CashbookTransfers\SaveCashbookTransferRequest;
use App\Http\Resources\CashbookTransfers\CashbookTransferResource;
use App\Services\CashbookTransfers\CashbookTransferService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CashbookTransferController extends Controller
{
    public function __construct(private readonly CashbookTransferService $cashbookTransferService) {}

    public function index(ListCashbookTransferRequest $request): JsonResponse
    {
        return ApiResponse::resource('Cashbook transfers retrieved successfully.', CashbookTransferResource::collection(
            $this->cashbookTransferService->paginate($request->validated(), false),
        ));
    }

    public function store(SaveCashbookTransferRequest $request): JsonResponse
    {
        return ApiResponse::resource('Cashbook transfer posted successfully.', CashbookTransferResource::make(
            $this->cashbookTransferService->post($request->validated(), $request->user(), false),
        ), status: 201);
    }

    public function show(int $cashbookTransfer): JsonResponse
    {
        return ApiResponse::resource('Cashbook transfer retrieved successfully.', CashbookTransferResource::make(
            $this->cashbookTransferService->find($cashbookTransfer, false),
        ));
    }
}
