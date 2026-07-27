<?php

namespace App\Policies;

use App\Models\User;
use App\Models\YearEndClosingSetting;
use App\Policies\Concerns\ClosingPolicyScope;

class AccountingClosingSettingsPolicy
{
    use ClosingPolicyScope;

    public function update(User $user, YearEndClosingSetting $model): bool
    {
        return $this->tenant($user, $model) && $user->hasPermission('accounting.closing_settings.update');
    }
}
