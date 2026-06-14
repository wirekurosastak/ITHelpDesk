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
    /**
     * @param  array{name: string, email: string, password: string, role_id?: int}  $attributes
     */
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

    /**
     * @param  array{role_id?: int, is_suspended?: bool}  $attributes
     */
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
            ]);

            return $user->refresh();
        });

        $this->forceLogout($user);

        return $user;
    }

    public function forceLogout(User $user): void
    {
        Cache::put($user->forceLogoutCacheKey(), true, $this->forceLogoutTtl());
    }

    public function forceLogoutAllNonAdmins(): int
    {
        $users = $this->nonAdminUsers();

        $users->each(fn (User $user): bool => Cache::put(
            $user->forceLogoutCacheKey(),
            true,
            $this->forceLogoutTtl()
        ));

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

    /**
     * @return Collection<int, User>
     */
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
