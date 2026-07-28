<?php

namespace App\Console\Commands;

use App\Models\Role;
use Database\Seeders\CashierPermissionReconciler;
use Illuminate\Console\Command;

class RepairUatCashierPermissions extends Command
{
    protected $signature = 'uat:repair-cashier-permissions';

    protected $description = 'Reconcile the system cashier role to its approved UAT allowlist';

    public function handle(CashierPermissionReconciler $reconciler): int
    {
        if (app()->environment('production')) {
            $this->error('STOP — This command is not allowed in production.');

            return self::FAILURE;
        }

        $role = Role::query()->whereNull('company_id')->where('name', 'cashier')->first();
        if (! $role) {
            $this->error('STOP — System cashier role was not found.');

            return self::FAILURE;
        }

        $allowlist = collect(CashierPermissionReconciler::ALLOWLIST);
        $before = $role->permissions()->pluck('name');
        $extra = $before->diff($allowlist)->values();
        $missing = $allowlist->diff($before)->values();
        $reconciler->run();
        $this->info('Cashier permissions reconciled.');
        $this->line('Extra removed: '.($extra->isEmpty() ? 'none' : $extra->implode(', ')));
        $this->line('Missing added: '.($missing->isEmpty() ? 'none' : $missing->implode(', ')));

        return self::SUCCESS;
    }
}
