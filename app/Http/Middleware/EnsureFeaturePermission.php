<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeaturePermission
{
    public function handle(Request $request, Closure $next, string $permission, ?string $updatePermission = null): Response
    {
        if ($updatePermission !== null && $request->filled('id')) {
            $permission = $updatePermission;
        }

        if (! $request->user()?->can($permission)) {
            return ApiResponse::error('Forbidden.', status: 403);
        }

        return $next($request);
    }
}
