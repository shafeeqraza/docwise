<?php

namespace App\Contracts\V1;

use App\Http\Requests\SuperAdminLoginRequest;
use App\Models\User;

interface SuperAdminLoginServiceInterface
{
    /**
     * Authenticate superadmin user and generate token.
     *
     * @param SuperAdminLoginRequest $req
     * @return array{token: string, token_type: string, user: User}
     * @throws \App\Exceptions\AuthenticationException
     */
    public function login(SuperAdminLoginRequest $req): array;

    /**
     * Get user model (for resource transformation).
     *
     * @param User $user
     * @return User
     */
    public function getUser(User $user): User;
}
