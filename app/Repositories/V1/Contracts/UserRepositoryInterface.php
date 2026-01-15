<?php

namespace App\Repositories\V1\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    /**
     * Find user by email.
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User;

    /**
     * Update user's last login timestamp.
     *
     * @param User $user
     * @return bool
     */
    public function updateLastLogin(User $user): bool;
}
