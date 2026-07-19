<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are invalid.',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $user->createToken('api')->plainTextToken,
            ],
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user()->currentAccessToken();

        if ($request->bearerToken() !== null) {
            $accessToken = PersonalAccessToken::findToken($request->bearerToken()) ?? $accessToken;
        }

        $accessToken?->delete();
        Auth::forgetGuards();

        return response()->json([
            'data' => ['message' => 'Logged out successfully.'],
        ]);
    }
}
