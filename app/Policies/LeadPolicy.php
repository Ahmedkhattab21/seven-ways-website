<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function view(User $user, Lead $lead): bool
    {
        return $this->inScope($user, $lead) && $user->hasPermission('leads.view');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->inScope($user, $lead) && $user->hasPermission('leads.update');
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $this->inScope($user, $lead) && $user->hasPermission('leads.convert');
    }

    private function inScope(User $user, Lead $lead): bool
    {
        return (int) $lead->company_id === (int) $user->company_id
            && ($user->isCompanyAdministrator() || ($lead->branch && $user->canAccessBranch($lead->branch)));
    }
}
