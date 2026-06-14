<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    /**
     * List all users (approved + pending), ordered: pending first.
     * Admin only.
     */
    public function index(): JsonResponse
    {
        $users = User::with('role')
            ->orderBy('is_approved')   // pending (0) first
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'email', 'role_id', 'is_approved', 'is_suspended', 'created_at']);

        return response()->json(['data' => $users]);
    }

    /**
     * Admin creates a new pre-approved user account.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|min:2|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id'  => 'sometimes|integer|in:1,2,3',
        ]);

        $user = User::create([
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'password'    => Hash::make($validated['password']),
            'role_id'     => $validated['role_id'] ?? 1,
            'is_approved' => true,
            'is_suspended'=> false,
        ]);

        return response()->json([
            'message' => "User \"{$user->name}\" created successfully.",
            'data'    => $user->load('role'),
        ], 201);
    }

    /**
     * Force-logout a user in real-time.
     */
    public function forceLogout(User $user): JsonResponse
    {
        $admin = auth('api')->user();
        if ($admin->id === $user->id) {
            return response()->json(['message' => 'Cannot force-logout yourself.'], 422);
        }
        if ($user->role_id === 3) {
            return response()->json(['message' => 'Cannot force-logout another admin.'], 422);
        }
        Cache::put('force_logout_' . $user->id, true, 86400); // Set flag for 24 hours
        return response()->json(['message' => "User \"{$user->name}\" has been logged out."]);
    }

    /**
     * Logout all non-admin users in real-time.
     */
    public function logoutAll(): JsonResponse
    {
        $users = User::where('role_id', '!=', 3)->get();
        foreach ($users as $u) {
            Cache::put('force_logout_' . $u->id, true, 86400);
        }
        return response()->json(['message' => "{$users->count()} user(s) have been logged out."]);
    }

    /**
     * Suspend a user by moving them to pending approvals.
     */
    public function suspend(User $user): JsonResponse
    {
        $admin = auth('api')->user();
        if ($admin->id === $user->id) {
            return response()->json(['message' => 'Cannot suspend yourself.'], 422);
        }
        if ($user->role_id === 3) {
            return response()->json(['message' => 'Cannot suspend another admin.'], 422);
        }
        $user->update(['is_approved' => false]);
        Cache::put('force_logout_' . $user->id, true, 86400); // Also log them out
        return response()->json(['message' => "User \"{$user->name}\" has been suspended."]);
    }

    /**
     * Suspend all non-admin users.
     */
    public function suspendAll(): JsonResponse
    {
        $count = User::where('role_id', '!=', 3)->update(['is_approved' => false]);
        $users = User::where('role_id', '!=', 3)->get();
        foreach ($users as $u) {
            Cache::put('force_logout_' . $u->id, true, 86400);
        }
        return response()->json(['message' => "{$count} user(s) have been suspended."]);
    }

    /**
     * Approve a pending user account.
     * Admin only.
     */
    public function approve(User $user): JsonResponse
    {
        if ($user->is_approved) {
            return response()->json(['message' => 'User is already approved.'], 422);
        }

        $user->update(['is_approved' => true]);

        return response()->json([
            'message' => "User \"{$user->name}\" has been approved.",
            'data'    => $user->load('role'),
        ]);
    }

    /**
     * Update a user's role or suspension status.
     * Admin only – cannot modify own account.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $admin = auth('api')->user();

        if ($admin->id === $user->id) {
            return response()->json(['message' => 'You cannot modify your own account here.'], 422);
        }

        $validated = $request->validate([
            'role_id'      => 'sometimes|integer|in:1,2,3',
            'is_suspended' => 'sometimes|boolean',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => "User \"{$user->name}\" updated.",
            'data'    => $user->refresh()->load('role'),
        ]);
    }

    /**
     * Delete a user account.
     * Admin only – cannot delete own account.
     */
    public function destroy(User $user): JsonResponse
    {
        $admin = auth('api')->user();

        if ($admin->id === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}
