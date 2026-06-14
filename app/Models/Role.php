<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    public const EMPLOYEE_ID = 1;

    public const IT_SUPPORT_ID = 2;

    public const ADMIN_ID = 3;

    public const EMPLOYEE = 'Employee';

    public const IT_SUPPORT = 'IT Support';

    public const ADMIN = 'Admin';

    public const ROLE_IDS = [
        'employee' => self::EMPLOYEE_ID,
        'it-support' => self::IT_SUPPORT_ID,
        'support' => self::IT_SUPPORT_ID,
        'admin' => self::ADMIN_ID,
    ];

    protected $fillable = ['name'];

    public static function idFor(string $role): ?int
    {
        return self::ROLE_IDS[strtolower($role)] ?? null;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
