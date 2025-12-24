<?php

namespace App\Services\V1\Auth;

use App\Contracts\V1\SuperAdminLogOutServiceInterface;
use App\Repositories\V1\AdminActionRepositoryInterface;
use App\Models\User;
use Illuminate\Http\Request;

class SuperAdminLogOutService implements SuperAdminLogOutServiceInterface
{
    /**
     * Create a new service instance.
     *
     */
    public function __construct() {}

    /**
     * Logout superadmin user by revoking current token.
     *
     * @param User $user
     * @param string $ipAddress
     * @param string|null $userAgent
     * @param Request|null $request
     * @return void
     */
    public function logout(User $user): void
    {
        // Revoke current token
        $token = $user->currentAccessToken();
        if ($token !== null) {
            /** @var \Laravel\Sanctum\PersonalAccessToken $token */
            $token->delete();
        }
    }
}
