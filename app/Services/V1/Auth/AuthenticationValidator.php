<?php

namespace App\Services\V1\Auth;

use App\Exceptions\AuthenticationException;
use App\Models\User;
use App\Repositories\V1\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthenticationValidator
{
    /**
     * Create a new service instance.
     *
     * @param UserRepositoryInterface $userRepository
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * Validate user credentials.
     *
     * @param string $email
     * @param string $password
     * @return User
     * @throws AuthenticationException
     */
    public function validateCredentials(string $email, string $password): User
    {
        $user = $this->userRepository->findByEmail($email);

        // Check if user exists
        if (!$user) {
            throw AuthenticationException::invalidCredentials();
        }

        // Verify password
        if (!Hash::check($password, $user->password)) {
            throw AuthenticationException::invalidCredentials();
        }

        return $user;
    }

    /**
     * Validate user is super admin.
     *
     * @param User $user
     * @param string $email
     * @param string $ipAddress
     * @return void
     * @throws AuthenticationException
     */
    public function validateSuperAdmin(User $user, string $email, string $ipAddress): void
    {
        if (!$user->isSuperAdmin()) {
            Log::warning('Non-superadmin attempt to access superadmin login', [
                'email' => $email,
                'ip' => $ipAddress,
                'user_id' => $user->id,
            ]);

            throw AuthenticationException::invalidCredentials();
        }
    }

    /**
     * Validate user account is active.
     *
     * @param User $user
     * @return void
     * @throws AuthenticationException
     */
    public function validateAccountActive(User $user): void
    {
        if ($user->trashed()) {
            throw AuthenticationException::accountDeactivated();
        }
    }
}
