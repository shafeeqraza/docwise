<?php

namespace App\Policies;

use App\Models\CompanyApiKey;
use App\Models\User;
use App\Policies\Concerns\ScopesToCompany;
use Illuminate\Auth\Access\Response;

/**
 * API keys are managed by company admins only. The company.admin middleware
 * enforces the same role; this policy keeps the rule next to the model.
 */
class CompanyApiKeyPolicy
{
    use ScopesToCompany;

    public function viewAny(User $user): Response
    {
        return $this->admin($user);
    }

    public function view(User $user, CompanyApiKey $apiKey): Response
    {
        return $this->manage($user, $apiKey);
    }

    public function create(User $user): Response
    {
        return $this->admin($user);
    }

    public function update(User $user, CompanyApiKey $apiKey): Response
    {
        return $this->manage($user, $apiKey);
    }

    public function delete(User $user, CompanyApiKey $apiKey): Response
    {
        return $this->manage($user, $apiKey);
    }

    public function regenerate(User $user, CompanyApiKey $apiKey): Response
    {
        return $this->manage($user, $apiKey);
    }

    private function manage(User $user, CompanyApiKey $apiKey): Response
    {
        if (! $this->sameCompany($user, $apiKey)) {
            return Response::denyAsNotFound();
        }

        return $this->admin($user);
    }

    private function admin(User $user): Response
    {
        return $user->isCompanyAdmin()
            ? Response::allow()
            : Response::deny('Only company admins can manage API keys.');
    }
}
