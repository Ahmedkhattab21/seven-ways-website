<?php

namespace App\Services;

use App\Models\User;

class UserDashboardProfileResolver
{
    public function profile(User $user): string
    {
        return match (true) {
            $user->hasRole('system_admin') => 'system_admin',
            $user->hasRole(['company_owner', 'general_manager']) => 'manager',
            $user->hasRole('accountant') => 'accountant',
            $user->hasRole('branch_manager') => 'branch_manager',
            default => 'generic',
        };
    }

    public function routeName(User $user): ?string
    {
        return match ($this->profile($user)) {
            'system_admin', 'manager' => 'dashboards.executive',
            'accountant' => 'accounting.dashboard',
            'branch_manager' => 'dashboard',
            default => $user->hasPermission('dashboard.view') ? 'dashboard' : null,
        };
    }

    public function canAccessRoute(User $user, string $routeName): bool
    {
        return match ($routeName) {
            'dashboards.executive' => $user->hasRole('system_admin')
                || $user->isCompanyAdministrator()
                || $user->hasPermission('dashboards.executive.view'),
            'accounting.dashboard' => $user->hasRole('system_admin')
                || $user->hasPermission('accounting.accounts.view'),
            'dashboard' => $user->hasRole('system_admin')
                || $user->hasPermission('dashboard.view'),
            default => false,
        };
    }
}
