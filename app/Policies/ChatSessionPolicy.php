<?php

namespace App\Policies;

use App\Models\ChatSession;
use App\Models\User;
use App\Policies\Concerns\ScopesToCompany;
use Illuminate\Auth\Access\Response;

/**
 * Governs admin-side access to chat transcripts. Widget routes authenticate
 * with an API key rather than a user, so they are scoped in ChatService instead.
 */
class ChatSessionPolicy
{
    use ScopesToCompany;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ChatSession $session): Response
    {
        return $this->sameCompany($user, $session)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function delete(User $user, ChatSession $session): Response
    {
        if (! $this->sameCompany($user, $session)) {
            return Response::denyAsNotFound();
        }

        return $user->isCompanyAdmin()
            ? Response::allow()
            : Response::deny('Only company admins can delete chat sessions.');
    }
}
