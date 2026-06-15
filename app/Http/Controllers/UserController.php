<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserAdministrationService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(private readonly UserAdministrationService $userAdministration) {}

    public function index(): JsonResponse
    {
        $users = User::with('role')
            ->orderBy('is_approved')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'email', 'role_id', 'is_approved', 'is_suspended', 'last_seen_at', 'last_ip', 'created_at'])
            ->each(fn (User $u) => $u->append('is_online'));

        return response()->json(['data' => $users]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userAdministration->createApprovedUser($request->validated());

        return response()->json([
            'message' => "User \"{$user->name}\" created successfully.",
            'data' => $user->load('role'),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['data' => $user->load('role')]);
    }

    public function forceLogout(User $user): JsonResponse
    {
        if ($error = $this->targetManagementError($user, 'force-logout')) {
            return $error;
        }

        $this->userAdministration->forceLogout($user);

        return response()->json(['message' => "User \"{$user->name}\" has been logged out."]);
    }

    public function logoutAll(): JsonResponse
    {
        $count = $this->userAdministration->forceLogoutAllNonAdmins();

        return response()->json(['message' => "{$count} user(s) have been logged out."]);
    }

    public function suspend(User $user): JsonResponse
    {
        if ($error = $this->targetManagementError($user, 'suspend')) {
            return $error;
        }

        $user = $this->userAdministration->suspend($user);

        return response()->json(['message' => "User \"{$user->name}\" has been suspended."]);
    }

    public function suspendAll(): JsonResponse
    {
        $count = $this->userAdministration->suspendAllNonAdmins();

        return response()->json(['message' => "{$count} user(s) have been suspended."]);
    }

    public function approve(User $user): JsonResponse
    {
        if ($error = $this->targetManagementError($user, 'approve')) {
            return $error;
        }

        if ($user->is_approved) {
            return response()->json(['message' => 'User is already approved.'], 422);
        }

        $user = $this->userAdministration->approve($user);

        return response()->json([
            'message' => "User \"{$user->name}\" has been approved.",
            'data' => $user->load('role'),
        ]);
    }

    public function resetPassword(\Illuminate\Http\Request $request, User $user): JsonResponse
    {
        if ($error = $this->targetManagementError($user, 'modify')) {
            return $error;
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password'])
        ]);
        
        $this->userAdministration->forceLogout($user);

        return response()->json([
            'message' => "Password for user \"{$user->name}\" has been reset successfully."
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        if ($error = $this->targetManagementError($user, 'modify')) {
            return $error;
        }

        $validated = $request->validated();
        $user = $this->userAdministration->update($user, $validated);

        if (($validated['is_suspended'] ?? false) === true) {
            $this->userAdministration->forceLogout($user);
        }

        return response()->json([
            'message' => "User \"{$user->name}\" updated.",
            'data' => $user->load('role'),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($error = $this->targetManagementError($user, 'delete')) {
            return $error;
        }

        if ($user->createdTickets()->exists() || $user->assignedTickets()->exists()) {
            return response()->json([
                'message' => 'Users with ticket history cannot be deleted. Suspend the account instead.',
            ], 422);
        }

        $user->delete();

        return response()->json(null, 204);
    }

    private function targetManagementError(User $target, string $action): ?JsonResponse
    {
        $admin = auth('api')->user();

        if ($admin->is($target)) {
            return response()->json(['message' => "You cannot {$action} your own account."], 422);
        }

        if ($target->role_id === Role::ADMIN_ID) {
            return response()->json(['message' => "Cannot {$action} another admin."], 422);
        }

        return null;
    }
}
