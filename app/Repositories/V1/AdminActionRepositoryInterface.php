<?php

namespace App\Repositories\V1;

use App\Models\User;

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
     * @return void
     */
    public function logAction(
        User $user,
        string $action,
        ?int $targetCompanyId = null,
        array $details = [],
        string $ipAddress = '',
        ?string $userAgent = null
    ): void;
}
