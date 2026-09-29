<?php

namespace App\Services\V1\Auth;

use App\Services\V1\Contracts\SuperAdminLoginServiceInterface;
use App\Exceptions\AuthenticationException;
use App\Http\Requests\SuperAdminLoginRequest;
use App\Models\User;
use App\Repositories\V1\Contracts\AdminActionRepositoryInterface;
use App\Repositories\V1\Contracts\UserRepositoryInterface;

class SuperAdminLoginService implements SuperAdminLoginServiceInterface
{
    /**
     * Create a new service instance.
     *
     * @param UserRepositoryInterface $userRepository
     * @param LoginAttemptService $loginAttemptService
     * @param AuthenticationValidator $authenticationValidator
     * @param TokenService $tokenService
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoginAttemptService $loginAttemptService,
        private readonly AuthenticationValidator $authenticationValidator,
        private readonly TokenService $tokenService
    ) {}

    /**
     * Authenticate superadmin user and generate token.
     *
     * @param SuperAdminLoginRequest $req
     * @return array{token: string, token_type: string, user: User}
     * @throws AuthenticationException
     */
    #[\Override]
    public function login(SuperAdminLoginRequest $req): array
    {
        [$email, $password] = [$req->email, $req->password];
        $ipAddress = $req->ip();

        // Check if login attempts exceeded limit
        if ($this->loginAttemptService->hasExceededLimit($email, $ipAddress)) {
            $remainingMinutes = $this->loginAttemptService->getLockoutExpirationMinutes($email, $ipAddress) ?? 1;
            throw AuthenticationException::tooManyAttempts($remainingMinutes);
        }

        try {
            // Validate credentials
            $user = $this->authenticationValidator->validateCredentials($email, $password);

            // Validate super admin status
            $this->authenticationValidator->validateSuperAdmin($user, $email, $ipAddress);

            // Validate account is active
            $this->authenticationValidator->validateAccountActive($user);
        } catch (AuthenticationException $e) {
            // Increment attempts on any authentication failure
            $this->loginAttemptService->incrementAttempts($email, $ipAddress);
            throw $e;
        }

        // Reset login attempts on successful authentication
        $this->loginAttemptService->resetAttempts($email, $ipAddress);

        // Generate token
        $token = $this->tokenService->generateSuperAdminToken($user);

        // Update last login timestamp
        $this->userRepository->updateLastLogin($user);

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ];
    }

    /**
     * Get user model (for resource transformation).
     *
     * @param User $user
     * @return User
     */
    #[\Override]
    public function getUser(User $user): User
    {
        return $user;
    }
}
