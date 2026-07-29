<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AccountantCashSessionPermissionReconciler;
use Illuminate\Console\Command;

class RepairUatCashSessionReviewPermissions extends Command
{
    protected $signature = 'uat:repair-cash-session-review-permissions';

    protected $description = 'Repair cash-session review permissions in isolated UAT';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('STOP — This command is not allowed in production.');

            return self::FAILURE;
        }
        $results = app(AccountantCashSessionPermissionReconciler::class)->reconcile();
        $review = Permission::query()->where('name', 'treasury.cash_sessions.review')->first();
        Role::query()->where('name', 'cashier')->get()->each(fn (Role $role) => $role->permissions()->detach($review?->id));
        foreach ($results as $result) {
            $role = $result['role'];
            $scope = $role->company_id ? 'company:'.$role->company_id : 'system';
            $this->line(sprintf(
                '%s (%s) added=[%s] removed=[%s]',
                $role->name,
                $scope,
                implode(',', $result['added']),
                implode(',', $result['removed'])
            ));
        }
        $this->info('Cash-session review permissions repaired without changing sessions or financial data.');

        return self::SUCCESS;
    }
}
