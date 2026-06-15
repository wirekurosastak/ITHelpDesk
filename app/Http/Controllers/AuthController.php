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

        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            return response()->json(['message' => 'This email is not registered.'], 404);
        }

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json(['message' => 'Incorrect password. Please try again.'], 401);
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
            'is_approved' => false,
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

    public function forgotPassword(\Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'This email is not registered.'], 404);
        }

        $existingTicket = \App\Models\Ticket::where('user_id', $user->id)
            ->where('title', 'Password Reset')
            ->whereIn('status', ['open', 'in_progress'])
            ->exists();

        if (!$existingTicket) {
            $accessCategory = \App\Models\Category::where('name', 'Access/Accounts')->first();
            
            \App\Models\Ticket::create([
                'title' => 'Password Reset',
                'description' => "User ({$user->email}) requested a password reset from the login screen.",
                'status' => 'open',
                'priority' => 'high',
                'user_id' => $user->id,
                'category_id' => $accessCategory ? $accessCategory->id : null,
            ]);
        }

        return response()->json([
            'message' => 'Password reset request has been sent to the IT administrators.'
        ]);
    }

    public function refresh(): JsonResponse
    {
        return $this->respondWithToken(auth('api')->refresh(), 'Token refreshed successfully.');
    }

    public function heartbeat(\Illuminate\Http\Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if ($user && $request->filled('ip')) {
            $ip = filter_var($request->input('ip'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
            if ($ip) {
                $user->update(['last_ip' => $ip]);
            }
        }

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
