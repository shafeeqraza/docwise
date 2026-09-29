<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\Response;

trait PolicyTestHelpers
{
    private const COMPANY = 1;

    private const OTHER_COMPANY = 2;

    private function user(UserRole $role, int $companyId = self::COMPANY, int $id = 10): User
    {
        return (new User)->forceFill([
            'id' => $id,
            'company_id' => $companyId,
            'role' => $role,
            'is_super_admin' => $role === UserRole::SUPER_ADMIN,
        ]);
    }

    private function assertAllowed(Response|bool $result): void
    {
        $this->assertTrue($result instanceof Response ? $result->allowed() : $result);
    }

    private function assertForbidden(Response $result): void
    {
        $this->assertTrue($result->denied());
        $this->assertNull($result->status(), 'Expected a plain 403 denial');
    }

    private function assertHiddenAsNotFound(Response $result): void
    {
        $this->assertTrue($result->denied());
        $this->assertSame(404, $result->status());
    }
}
