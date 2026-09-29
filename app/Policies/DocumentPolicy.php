<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;
use App\Policies\Concerns\ScopesToCompany;
use Illuminate\Auth\Access\Response;

class DocumentPolicy
{
    use ScopesToCompany;

    /**
     * Any member of the company may list its documents.
     * The listing itself is scoped by current_company_id.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Document $document): Response
    {
        return $this->sameCompany($user, $document)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Company admins and agents may upload; api users are read-only.
     */
    public function create(User $user): Response
    {
        return $user->isCompanyAdmin() || $user->role === UserRole::AGENT
            ? Response::allow()
            : Response::deny('You are not allowed to upload documents.');
    }

    /**
     * Company admins may delete any document in their company;
     * everyone else may delete only documents they uploaded.
     */
    public function delete(User $user, Document $document): Response
    {
        if (! $this->sameCompany($user, $document)) {
            return Response::denyAsNotFound();
        }

        return $user->isCompanyAdmin() || (int) $document->uploaded_by === $user->id
            ? Response::allow()
            : Response::deny('Only company admins or the uploader can delete this document.');
    }
}
