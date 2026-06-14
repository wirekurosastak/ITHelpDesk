<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserAdministrationService
{
    public function createApprovedUser(array $attributes): User
    {
        return DB::transaction(fn (): User => User::create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => Hash::make($attributes['password']),
            'role_id' => $attributes['role_id'] ?? Role::EMPLOYEE_ID,
            'is_approved' => true,
            'is_suspended' => false,
        ]));
    }

    public function approve(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            $user->update([
                'is_approved' => true,
                'is_suspended' => false,
            ]);

            return $user->refresh();
        });
    }

    public function update(User $user, array $attributes): User
    {
        return DB::transaction(function () use ($user, $attributes): User {
            $user->update($attributes);

            return $user->refresh();
        });
    }

    public function suspend(User $user): User
    {
        $user = DB::transaction(function () use ($user): User {
            $user->update([
                'is_approved' => false,
                'is_suspended' => true,
                'last_seen_at' => null,
            ]);

            return $user->refresh();
        });

        $this->forceLogout($user);

        return $user;
    }

    public function forceLogout(User $user): void
    {
        Cache::put($user->forceLogoutCacheKey(), true, $this->forceLogoutTtl());
        $user->update(['last_seen_at' => null]);
    }

    public function forceLogoutAllNonAdmins(): int
    {
        $users = $this->nonAdminUsers();

        $users->each(fn (User $user): bool => Cache::put(
            $user->forceLogoutCacheKey(),
            true,
            $this->forceLogoutTtl()
        ));

        User::query()->whereKey($users->modelKeys())->update(['last_seen_at' => null]);

        return $users->count();
    }

    public function suspendAllNonAdmins(): int
    {
        $users = DB::transaction(function (): Collection {
            $users = $this->nonAdminUsers();

            User::query()
                ->whereKey($users->modelKeys())
                ->update([
                    'is_approved' => false,
                    'is_suspended' => true,
                    'last_seen_at' => null,
                ]);

            return $users;
        });

        $users->each(fn (User $user): bool => Cache::put(
            $user->forceLogoutCacheKey(),
            true,
            $this->forceLogoutTtl()
        ));

        return $users->count();
    }

    private function nonAdminUsers(): Collection
    {
        return User::query()
            ->where('role_id', '!=', Role::ADMIN_ID)
            ->get(['id']);
    }

    private function forceLogoutTtl(): int
    {
        return (int) config('security.force_logout_ttl', 86400);
    }
}
