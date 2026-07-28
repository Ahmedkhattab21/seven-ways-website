<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class CashierPermissionReconciler extends Seeder
{
    public const ALLOWLIST = [
        'dashboard.view',
        'treasury.cash_boxes.view', 'treasury.balances.view',
        'treasury.transfers.view', 'treasury.transfers.create', 'treasury.transfers.submit',
        'treasury.cash_sessions.view', 'treasury.cash_sessions.open',
        'treasury.cash_sessions.count', 'treasury.cash_sessions.submit',
        'treasury.cash_receipts.view', 'treasury.cash_receipts.create', 'treasury.cash_receipts.submit',
        'treasury.cash_payments.view', 'treasury.cash_payments.create', 'treasury.cash_payments.submit',
        'treasury.cheques.view', 'treasury.cheques.create', 'treasury.cheques.submit',
    ];

    public function run(): void
    {
        $role = Role::query()->whereNull('company_id')->where('name', 'cashier')->first();
        if (! $role) {
            return;
        }

        $role->permissions()->sync(Permission::query()->whereIn('name', self::ALLOWLIST)->pluck('id'));
    }
}
