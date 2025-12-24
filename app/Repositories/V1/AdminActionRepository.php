<?php

namespace App\Repositories\V1;

use App\Models\AdminAction;
use App\Models\User;

class AdminActionRepository implements AdminActionRepositoryInterface
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
    ): void {
        AdminAction::create([
            'admin_user_id' => $user->id,
            'target_company_id' => $targetCompanyId,
            'action' => $action,
            'details' => $details,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
