<?php

namespace App\Policies;

use App\Models\QualityChecklistTemplate;
use App\Models\User;

class QualityChecklistTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('quality_checks.view') || $user->hasPermission('quality_checks.manage_templates');
    }

    public function view(User $user, QualityChecklistTemplate $template): bool
    {
        return (int) $user->company_id === (int) $template->company_id && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('quality_checks.manage_templates');
    }

    public function update(User $user, QualityChecklistTemplate $template): bool
    {
        return (int) $user->company_id === (int) $template->company_id
            && $user->hasPermission('quality_checks.manage_templates');
    }
}
