<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cashbooks\SaveCashbookRequest;
use App\Http\Requests\Cashbooks\ToggleCashbookActiveRequest;
use App\Http\Resources\Cashbooks\CashbookResource;
use App\Models\Cashbook;
use App\Services\Cashbooks\CashbookService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashbookController extends Controller
{
    public function __construct(private readonly CashbookService $cashbookService) {}

    public function index(Request $request): JsonResponse
    {
        $cashbooks = $this->cashbookService->paginate($request->only(['search', 'type', 'is_active', 'page', 'per_page']));

        return ApiResponse::resource('Cashbooks retrieved successfully.', CashbookResource::collection($cashbooks));
    }

    public function store(SaveCashbookRequest $request): JsonResponse
    {
        $cashbook = $this->cashbookService->save($request->validated());

        return ApiResponse::resource('Cashbook saved successfully.', $cashbook, status: filled($request->input('id')) ? 200 : 201);
    }

    public function show(Cashbook $cashbook): JsonResponse
    {
        return ApiResponse::resource('Cashbook retrieved successfully.', CashbookResource::make($cashbook));
    }

    public function destroy(Cashbook $cashbook): JsonResponse
    {
        $this->cashbookService->delete($cashbook);

        return ApiResponse::success('Cashbook deleted successfully.');
    }

    public function toggleActive(ToggleCashbookActiveRequest $request, Cashbook $cashbook): JsonResponse
    {
        $cashbook = $this->cashbookService->toggleActive($cashbook, (bool) $request->validated('is_active'));

        return ApiResponse::resource('Cashbook active status updated successfully.', $cashbook);
    }
}
