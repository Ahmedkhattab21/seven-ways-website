<?php

namespace App\Policies;

use App\Models\BranchSetting;
use App\Models\User;

class BranchSettingPolicy
{
    public function update(User $user, BranchSetting $settings): bool
    {
        return (int) $settings->branch?->company_id === (int) $user->company_id
            && $user->hasPermission('branch_settings.manage');
    }
}
