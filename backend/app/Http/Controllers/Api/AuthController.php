<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        // Pesan sama untuk email salah & password salah → tidak membocorkan email mana yang terdaftar.
        if ($user === null || ! Hash::check((string) $request->validated('password'), $user->password)) {
            return ApiResponse::error('Email atau password salah', 401);
        }

        $expiresAt = now()->addMinutes((int) config('sanctum.expiration', 480));
        $token = $user->createToken(
            name: (string) $request->validated('device_name', 'web'),
            abilities: [$user->role->value],
            expiresAt: $expiresAt,
        );

        return ApiResponse::success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => (new UserResource($user))->resolve($request),
        ], 'Login berhasil');
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        // Revoke: token dihapus dari DB → request berikutnya dengan token ini dapat 401.
        // (API ini murni Bearer token — sanctum.guard = [] — jadi token selalu PersonalAccessToken.)
        $user->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logout berhasil');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()));
    }
}
