<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\Menus\ListMenuRequest;
use App\Http\Resources\Menus\MenuResource;
use App\Models\Menu;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    public function index(ListMenuRequest $request): JsonResponse
    {
        $filters = array_merge($request->validated(), [
            'is_active' => true,
            'is_available' => true,
        ]);

        $query = Menu::query()
            ->with('menuCategory')
            ->filter($filters)
            ->latest();

        $menus = isset($filters['page'])
            ? $query->paginate((int) ($filters['per_page'] ?? 15))
            : $query->get();

        return ApiResponse::resource('Menus retrieved successfully.', MenuResource::collection($menus));
    }
}
