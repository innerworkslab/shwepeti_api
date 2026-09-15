<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogs\AuditLogResource;
use App\Services\AuditLogs\AuditLogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OwenIt\Auditing\Models\Audit;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index(Request $request): JsonResponse
    {
        $auditLogs = $this->auditLogService->paginate($request->only([
            'event',
            'user_id',
            'auditable_type',
            'auditable_id',
            'date_from',
            'date_to',
            'page',
            'per_page',
        ]));

        return ApiResponse::resource(
            'Audit logs retrieved successfully.',
            AuditLogResource::collection($auditLogs),
        );
    }

    public function show(Audit $auditLog): JsonResponse
    {
        return ApiResponse::resource(
            'Audit log retrieved successfully.',
            AuditLogResource::make($auditLog->load('user')),
        );
    }
}
