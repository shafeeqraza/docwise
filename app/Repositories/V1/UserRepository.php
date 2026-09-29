<?php

namespace App\Repositories\V1;

use App\Models\User;
use App\Repositories\V1\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Find user by email.
     *
     * @param string $email
     * @return User|null
     */
    #[\Override]
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Update user's last login timestamp.
     *
     * @param User $user
     * @return bool
     */
    #[\Override]
    public function updateLastLogin(User $user): bool
    {
        return $user->update([
            'last_login_at' => now(),
        ]);
    }
}
