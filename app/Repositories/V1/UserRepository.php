<?php

namespace App\Repositories\V1;

use App\Models\User;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Find user by email.
     *
     * @param string $email
     * @return User|null
     */
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
    public function updateLastLogin(User $user): bool
    {
        return $user->update([
            'last_login_at' => now(),
        ]);
    }
}
