<?php

namespace App\Policies;

use App\Models\FiscalYear;
use App\Models\User;

class YearEndClosingPolicy
{
    public function execute(User $user, FiscalYear $year): bool
    {
        return $user->company_id === $year->company_id && $year->status === 'soft_closed' && $user->hasPermission('accounting.year_end.execute');
    }
}
