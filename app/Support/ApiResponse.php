<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    public static function success(
        string $message,
        mixed $data = null,
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ], $status);
    }

    public static function resource(
        string $message,
        JsonResource|ResourceCollection $resource,
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        return self::success(
            $message,
            $resource->resolve(),
            array_merge(self::paginationMeta($resource), $meta),
            $status,
        );
    }

    public static function error(
        string $message,
        array $errors = [],
        array $meta = [],
        int $status = 400,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'meta' => $meta,
        ], $status);
    }

    /**
     * @return array<string, int>
     */
    private static function paginationMeta(JsonResource|ResourceCollection $resource): array
    {
        if (! $resource instanceof ResourceCollection || ! $resource->resource instanceof LengthAwarePaginator) {
            return [];
        }

        return [
            'current_page' => $resource->resource->currentPage(),
            'per_page' => $resource->resource->perPage(),
            'total' => $resource->resource->total(),
            'last_page' => $resource->resource->lastPage(),
        ];
    }
}
