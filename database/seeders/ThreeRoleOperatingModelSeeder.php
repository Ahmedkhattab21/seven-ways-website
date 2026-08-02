<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ThreeRoleOperatingModelSeeder extends Seeder
{
    public function run(): void
    {
        Permission::query()->updateOrCreate(
            ['name' => 'branches.assign_responsible'],
            ['display_name' => 'تعيين مسؤول تشغيل الفرع']
        );

        $all = Permission::query()->pluck('id', 'name');

        $this->reconcileRole('branch_manager', 'مسؤول الفرع', 'branch', $this->branchPermissions($all));
        $this->reconcileRole('accountant', 'المحاسب', 'company', $this->accountantPermissions($all));
        $this->reconcileRole('general_manager', 'المدير العام', 'company', $all->values());
        $this->reconcileRole('company_owner', 'المدير العام / مالك الشركة', 'company', $all->values());
    }

    private function reconcileRole(string $name, string $displayName, string $scope, Collection $permissionIds): void
    {
        Role::query()->where('name', $name)->get()->each(function (Role $role) use (
            $displayName,
            $scope,
            $permissionIds
        ): void {
            $role->forceFill([
                'display_name' => $displayName,
                'scope' => $scope,
                'is_active' => true,
            ])->save();
            $role->permissions()->sync($permissionIds->unique()->values());
        });
    }

    private function branchPermissions(Collection $permissions): Collection
    {
        $allowedExact = [
            'dashboard.view', 'branches.view', 'customers.view', 'customers.create', 'customers.update',
            'customers.manage_contacts', 'customers.manage_addresses', 'customers.manage_notes',
            'customers.manage_attachments', 'vehicles.view', 'vehicles.create', 'vehicles.update',
            'vehicles.transfer_ownership', 'vehicles.manage_attachments', 'products.view',
            'products.manage_branch_availability', 'products.manage_branch_prices', 'services.view',
            'service_packages.view', 'quotations.view', 'quotations.create', 'quotations.update',
            'quotations.submit', 'quotations.send', 'sales_invoices.view', 'sales_invoices.create',
            'sales_invoices.update', 'sales_invoices.submit', 'sales_invoices.issue', 'sales_invoices.print',
            'sales_invoices.direct_sale', 'sales_invoices.share', 'customer_payments.view', 'customer_payments.record',
            'customer_payments.approve', 'customer_payments.allocate', 'customer_payments.print', 'customer_statements.view',
            'sales_credit_notes.view', 'sales_credit_notes.create', 'sales_credit_notes.print',
            'treasury.cash_boxes.view', 'treasury.balances.view', 'treasury.cash_sessions.view',
            'treasury.cash_sessions.open', 'treasury.cash_sessions.count', 'treasury.cash_sessions.submit',
            'treasury.cash_receipts.view', 'treasury.cash_receipts.create', 'treasury.cash_receipts.submit',
            'treasury.cash_payments.view', 'treasury.cash_payments.create', 'treasury.cash_payments.submit',
            'treasury.cheques.view', 'treasury.cheques.create', 'treasury.cheques.submit',
            'purchase_requisitions.view', 'purchase_requisitions.create', 'purchase_requisitions.update',
            'purchase_requisitions.submit', 'purchase_orders.view', 'goods_receipts.view',
            'goods_receipts.create', 'goods_receipts.update', 'goods_receipts.post', 'warehouses.view',
            'inventory.view', 'inventory.count', 'stock_transfers.view', 'stock_transfers.create',
            'stock_transfers.receive',
        ];

        return $permissions->only($allowedExact)->values();
    }

    private function accountantPermissions(Collection $permissions): Collection
    {
        $allowedPrefixes = [
            'accounting.', 'reports.', 'supplier_invoices.', 'supplier_payments.',
            'supplier_credit_notes.', 'supplier_statements.', 'accounts_payable.',
            'accounts_receivable.', 'treasury.', 'banks.', 'bank_accounts.',
        ];
        $allowedExact = [
            'dashboard.view', 'branches.view', 'customers.view', 'products.view', 'sales_invoices.view',
            'sales_invoices.print', 'sales_invoices.share', 'sales_invoices.view_shares',
            'customer_payments.view', 'customer_payments.allocate',
            'customer_statements.view', 'suppliers.view', 'suppliers.view_balance',
            'taxes.view', 'taxes.manage', 'fiscal_years.view', 'document_sequences.view',
        ];
        $denied = [
            'treasury.cash_sessions.open', 'treasury.cash_sessions.count', 'treasury.cash_sessions.submit',
            'treasury.cash_sessions.approve', 'treasury.cash_sessions.close', 'treasury.cash_sessions.reopen',
            'treasury.approval_limits.manage', 'treasury.approval_limits.unlimited',
            'accounting.opening_balances.approve', 'accounting.opening_balances.mark_ready',
            'accounting.opening_balances.post',
        ];

        return $permissions->filter(function ($id, string $name) use ($allowedPrefixes, $allowedExact, $denied) {
            if (in_array($name, $denied, true)) {
                return false;
            }

            return in_array($name, $allowedExact, true)
                || collect($allowedPrefixes)->contains(fn (string $prefix) => str_starts_with($name, $prefix));
        })->values();
    }
}
