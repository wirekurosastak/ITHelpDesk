<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role_id', 'is_approved', 'is_suspended', 'last_seen_at', 'last_ip'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role_id' => 'integer',
            'is_approved' => 'boolean',
            'is_suspended' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->greaterThan(now()->subMinutes(5));
    }

    public function getIsOnlineAttribute(): bool
    {
        return $this->isOnline();
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function isEmployee(): bool
    {
        return $this->role_id === Role::EMPLOYEE_ID;
    }

    public function isSupportStaff(): bool
    {
        return in_array($this->role_id, [Role::IT_SUPPORT_ID, Role::ADMIN_ID], true);
    }

    public function isAdmin(): bool
    {
        return $this->role_id === Role::ADMIN_ID;
    }

    public function forceLogoutCacheKey(): string
    {
        return "force_logout:{$this->getKey()}";
    }

    public function hasAnyRole(array $roles): bool
    {
        $roleIds = array_filter(array_map(fn (string $role): ?int => Role::idFor($role), $roles));

        return in_array($this->role_id, $roleIds, true);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function createdTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'user_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }
}
