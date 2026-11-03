<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(PermissionCatalog $catalog): JsonResponse
    {
        return ApiResponse::success('Permissions retrieved successfully.', $catalog->groups());
    }
}
