<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\SaveUserRequest;
use App\Http\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Users\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function index(Request $request): JsonResponse
    {
        $users = $this->userService->paginate($request->only(['search', 'role', 'is_active', 'page', 'per_page']));

        return ApiResponse::resource(
            'Users retrieved successfully.',
            UserResource::collection($users),
        );
    }

    public function store(SaveUserRequest $request): JsonResponse
    {
        $user = $this->userService->save($request->validated(), $request->user());

        return ApiResponse::resource(
            'User saved successfully.',
            $user,
            status: filled($request->input('id')) ? 200 : 201,
        );
    }

    public function show(User $user): JsonResponse
    {
        return ApiResponse::resource('User retrieved successfully.', UserResource::make($user));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->userService->delete($user, $request->user());

        return ApiResponse::success('User deleted successfully.');
    }

    public function restore(Request $request, int $user): JsonResponse
    {
        $restoredUser = $this->userService->restore($user, $request->user());

        return ApiResponse::resource('User restored successfully.', UserResource::make($restoredUser));
    }
}
