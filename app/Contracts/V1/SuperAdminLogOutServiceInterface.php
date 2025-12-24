<?php

namespace App\Contracts\V1;

use App\Http\Requests\SuperAdminLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface SuperAdminLogOutServiceInterface
{
    /**
     * Logout superadmin user by revoking current token.
     *
     * @param User $user
     * @return void
     */
    public function logout(User $user): void;
}
