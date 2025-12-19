<?php

namespace App\Services\V1\Auth;

use App\Contracts\V1\SuperAdminLoginServiceInterface;
use App\Exceptions\AuthenticationException;
use App\Http\Requests\SuperAdminLoginRequest;
use App\Models\User;
use App\Repositories\V1\AdminActionRepositoryInterface;
use App\Repositories\V1\UserRepositoryInterface;

class SuperAdminLoginService implements SuperAdminLoginServiceInterface
{
    /**
     * Create a new service instance.
     *
     * @param UserRepositoryInterface $userRepository
     * @param AdminActionRepositoryInterface $adminActionRepository
     * @param LoginAttemptService $loginAttemptService
     * @param AuthenticationValidator $authenticationValidator
     * @param TokenService $tokenService
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly AdminActionRepositoryInterface $adminActionRepository,
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

        // Log admin action
        $this->adminActionRepository->logAction(
            user: $user,
            action: 'auth.login',
            targetCompanyId: null,
            details: [
                'email' => $user->email,
                'ip_address' => $ipAddress,
                'user_agent' => $req->userAgent(),
            ],
            ipAddress: $ipAddress,
            userAgent: $req->userAgent()
        );

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
    public function getUser(User $user): User
    {
        return $user;
    }
}
