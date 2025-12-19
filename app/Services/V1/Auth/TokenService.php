<?php

namespace App\Services\V1\Auth;

use App\Models\User;

class TokenService
{
    /**
     * Generate authentication token for user.
     *
     * @param User $user
     * @param string $tokenName
     * @param array $abilities
     * @return string
     */
    public function generateToken(User $user, string $tokenName = 'api-access', array $abilities = []): string
    {
        return $user->createToken($tokenName, $abilities)->plainTextToken;
    }

    /**
     * Generate superadmin authentication token.
     *
     * @param User $user
     * @return string
     */
    public function generateSuperAdminToken(User $user): string
    {
        return $this->generateToken(
            user: $user,
            tokenName: 'superadmin-api-access',
            abilities: [
                'super_admin',
                'company_id' => $user->company_id,
                'role' => $user->role,
            ]
        );
    }
}
