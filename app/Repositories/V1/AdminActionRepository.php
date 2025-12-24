<?php

namespace App\Repositories\V1;

use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Http\Request;

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
    ): void {
        // Merge request information into details if request is provided
        if ($request) {
            $requestDetails = [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
                'referer' => $request->header('referer'),
            ];
            $details = array_merge($requestDetails, $details);
        }

        AdminAction::create([
            'admin_user_id' => $user->id,
            'target_company_id' => $targetCompanyId,
            'action' => $action,
            'details' => $details,
            'ip_address' => $ipAddress ?: ($request?->ip() ?? ''),
            'user_agent' => $userAgent ?: ($request?->userAgent()),
        ]);
    }
}
