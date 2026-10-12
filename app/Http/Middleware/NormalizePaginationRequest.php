<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizePaginationRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->has('per_page') && isset($request->perPage)) {
            $request->merge([
                'per_page' => $request->perPage,
            ]);
        }

        return $next($request);
    }
}
