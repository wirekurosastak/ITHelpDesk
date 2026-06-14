<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        $user = auth('api')->user();

        if ($user->is_suspended) {
            auth('api')->logout();

            return response()->json([
                'message' => 'Your account has been suspended. Please contact your administrator.',
            ], 403);
        }

        if (! $user->is_approved) {
            auth('api')->logout();

            return response()->json([
                'message' => 'Your account is pending admin approval. You will be notified when access is granted.',
            ], 403);
        }

        return $this->respondWithToken($token, 'Login successful.');
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => Role::EMPLOYEE_ID,
            'is_approved' => false,   // must be approved by Admin before login
        ]);

        return response()->json([
            'message' => 'Registration successful. Your account is pending admin approval.',
        ], 201);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'data' => auth('api')->user()->load('role'),
        ]);
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out.']);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = auth('api')->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function refresh(): JsonResponse
    {
        return $this->respondWithToken(auth('api')->refresh(), 'Token refreshed successfully.');
    }

    /** Lightweight ping – UpdateLastSeen middleware already wrote last_seen_at. */
    public function heartbeat(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function respondWithToken(string $token, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => auth('api')->user()->load('role'),
        ]);
    }
}
