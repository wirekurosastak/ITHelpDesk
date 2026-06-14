<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_role_helpers_describe_user_permissions(): void
    {
        $employee = new User(['role_id' => Role::EMPLOYEE_ID]);
        $support = new User(['role_id' => Role::IT_SUPPORT_ID]);
        $admin = new User(['role_id' => Role::ADMIN_ID]);

        $this->assertTrue($employee->isEmployee());
        $this->assertFalse($employee->isSupportStaff());

        $this->assertTrue($support->isSupportStaff());
        $this->assertFalse($support->isAdmin());

        $this->assertTrue($admin->isSupportStaff());
        $this->assertTrue($admin->isAdmin());
    }
}
