<?php

namespace App\Policies;

use App\Models\FinancialReportDefinition;
use App\Models\User;

class FinancialReportDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.financial_reports.manage_definitions');
    }

    public function update(User $user, FinancialReportDefinition $definition): bool
    {
        return $user->company_id === $definition->company_id
            && ! $definition->is_system
            && $user->hasPermission('accounting.financial_reports.manage_definitions');
    }
}
