<?php

namespace App\Http\Middleware;

use App\Enums\UserPortalAccessEnum;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserPortalAccess
{
    public function handle(Request $request, Closure $next, string $portal): Response
    {
        $user = $request->user();
        $portalAccess = UserPortalAccessEnum::tryFrom($portal);

        if (! $user || ! $portalAccess || ! $user->canAccessPortal($portalAccess)) {
            return ApiResponse::error('Forbidden.', status: 403);
        }

        return $next($request);
    }
}
