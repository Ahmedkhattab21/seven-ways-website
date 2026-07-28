<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\ProductionReferenceSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CashierRoleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_production_reference_seeder_creates_branch_scoped_cashier_with_operational_permissions(): void
    {
        app(ProductionReferenceSeeder::class)->run();
        app(ProductionReferenceSeeder::class)->run();

        $cashiers = Role::query()->whereNull('company_id')->where('name', 'cashier')->get();
        $this->assertCount(1, $cashiers);
        $cashier = $cashiers->first();
        $this->assertSame('branch', $cashier->scope);
        $this->assertTrue($cashier->is_system);
        $this->assertTrue($cashier->is_active);

        $permissions = $cashier->permissions()->pluck('name');
        foreach ([
            'dashboard.view', 'treasury.cash_boxes.view', 'treasury.balances.view',
            'treasury.cash_sessions.view', 'treasury.cash_sessions.open',
            'treasury.cash_sessions.count', 'treasury.cash_sessions.submit',
            'treasury.cash_receipts.view', 'treasury.cash_receipts.create',
            'treasury.cash_receipts.submit', 'treasury.cash_payments.view',
            'treasury.cash_payments.create', 'treasury.cash_payments.submit',
            'treasury.cheques.view', 'treasury.cheques.create', 'treasury.cheques.submit',
        ] as $permission) {
            $this->assertTrue($permissions->contains($permission), "Missing {$permission}");
        }
        foreach (['approve', 'post', 'reverse', 'users.manage', 'roles.manage', 'companies.update'] as $suffix) {
            $this->assertFalse($permissions->contains($suffix) || $permissions->contains('treasury.'.$suffix));
        }
    }

    public function test_cashier_reconciler_removes_bank_reconciliation_permissions(): void
    {
        app(ProductionReferenceSeeder::class)->run();
        $role = Role::query()->whereNull('company_id')->where('name', 'cashier')->firstOrFail();
        $leaked = Permission::query()->firstOrCreate(['name' => 'treasury.bank_statements.view']);
        $role->permissions()->syncWithoutDetaching([$leaked->id]);

        Artisan::call('uat:repair-cashier-permissions');

        $this->assertFalse($role->fresh()->permissions()->where('name', 'treasury.bank_statements.view')->exists());
        $this->assertFalse($role->fresh()->permissions()->where('name', 'treasury.reconciliation.view')->exists());
    }
}
