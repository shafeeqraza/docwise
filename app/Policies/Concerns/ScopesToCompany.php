<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait ScopesToCompany
{
    /**
     * Whether the user may act within the model's company.
     * Super admins can reach every company.
     */
    protected function sameCompany(User $user, Model $model): bool
    {
        return $user->canAccessCompany((int) $model->company_id);
    }
}
