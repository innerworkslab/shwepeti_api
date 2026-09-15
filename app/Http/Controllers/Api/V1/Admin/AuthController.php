<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Users\UserResource;
use App\Services\Auth\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated(), $request);

        return ApiResponse::success('Login successful.', [
            'token_type' => 'Bearer',
            'token' => $result['token'],
            'user' => UserResource::make($result['user'])->resolve(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request);

        return ApiResponse::success('Logout successful.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::resource('Profile retrieved successfully.', UserResource::make($request->user()));
    }
}
