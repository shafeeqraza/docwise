<?php

namespace App\Services\V1\Auth;

use App\Contracts\V1\SuperAdminLogOutServiceInterface;
use App\Repositories\V1\AdminActionRepositoryInterface;
use App\Models\User;

class SuperAdminLogOutService implements SuperAdminLogOutServiceInterface
{
    /**
     * Create a new service instance.
     *
     * @param AdminActionRepositoryInterface $adminActionRepository
     */
    public function __construct(
        private readonly AdminActionRepositoryInterface $adminActionRepository
    ) {}



    /**
     * Logout superadmin user by revoking current token.
     *
     * @param User $user
     * @param string $ipAddress
     * @param string|null $userAgent
     * @return void
     */
    public function logout(User $user, string $ipAddress, ?string $userAgent = null): void
    {
        // Log admin action before revoking token
        $this->adminActionRepository->logAction(
            user: $user,
            action: 'auth.logout',
            targetCompanyId: null,
            details: [
                'email' => $user->email,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ],
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        // Revoke current token
        $token = $user->currentAccessToken();
        if ($token !== null) {
            /** @var \Laravel\Sanctum\PersonalAccessToken $token */
            $token->delete();
        }
    }

    /**
     * Get formatted user data for response.
     *
     * @param User $user
     * @return array
     */
    public function getUserData(User $user): array
    {
        return $this->formatUserData($user);
    }

    /**
     * Format user data for API response.
     *
     * @param User $user
     * @return array
     */
    private function formatUserData(User $user): array
    {
        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_super_admin' => $user->is_super_admin,
            'can_impersonate' => $user->can_impersonate,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at->toIso8601String(),
        ];
    }
}
