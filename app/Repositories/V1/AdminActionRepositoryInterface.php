<?php

namespace App\Repositories\V1;

use App\Models\User;
use Illuminate\Http\Request;

interface AdminActionRepositoryInterface
{
    /**
     * Log admin action.
     *
     * @param User $user
     * @param string $action
     * @param int|null $targetCompanyId
     * @param array $details
     * @param string $ipAddress
     * @param string|null $userAgent
     * @param Request|null $request
     * @return void
     */
    public function logAction(
        User $user,
        string $action,
        ?int $targetCompanyId = null,
        array $details = [],
        string $ipAddress = '',
        ?string $userAgent = null,
        ?Request $request = null
    ): void;
}
