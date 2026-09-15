<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * @return array{token: string, user: User}
     */
    public function login(array $credentials, Request $request): array
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive.'],
            ]);
        }

        DB::beginTransaction();

        try {
            $user->forceFill(['last_login_at' => now()])->save();
            $token = $user->createToken($request->userAgent() ?: 'api-token');

            DB::commit();

            return [
                'token' => $token->plainTextToken,
                'user' => $user->refresh(),
            ];
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }
    }

    public function logout(Request $request): void
    {
        $request->user()?->currentAccessToken()?->delete();
    }
}
