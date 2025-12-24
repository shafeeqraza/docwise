<?php

namespace App\Contracts\V1;

use App\Http\Requests\SuperAdminLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

interface SuperAdminLogOutServiceInterface
{
    /**
     * Logout superadmin user by revoking current token.
     *
     * @param User $user
     * @param string $ipAddress
     * @param string|null $userAgent
     * @return void
     */
    public function logout(User $user, string $ipAddress, ?string $userAgent = null): void;
}
