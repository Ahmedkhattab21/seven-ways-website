<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function view(User $user, Company $company): bool
    {
        return $user->company_id === $company->id && $user->hasPermission('companies.view');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->company_id === $company->id
            && $user->hasRole(['company_owner', 'general_manager'])
            && $user->hasPermission('companies.update');
    }
}
