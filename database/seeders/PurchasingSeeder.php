<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;

class PurchasingSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.disable', 'suppliers.view_balance',
            'purchase_requisitions.view', 'purchase_requisitions.create', 'purchase_requisitions.update',
            'purchase_requisitions.submit', 'purchase_requisitions.approve', 'purchase_requisitions.reject',
            'purchase_requisitions.cancel', 'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update',
            'purchase_orders.submit', 'purchase_orders.approve', 'purchase_orders.send', 'purchase_orders.cancel',
            'purchase_orders.override_price', 'purchase_orders.override_quantity', 'goods_receipts.view',
            'goods_receipts.create', 'goods_receipts.update', 'goods_receipts.inspect', 'goods_receipts.post',
            'goods_receipts.cancel', 'goods_receipts.override_tolerance', 'goods_receipts.view_cost',
            'purchase_returns.view', 'purchase_returns.create', 'purchase_returns.approve', 'purchase_returns.post',
            'purchase_returns.cancel', 'supplier_invoices.view', 'supplier_invoices.create', 'supplier_invoices.update',
            'supplier_invoices.submit', 'supplier_invoices.approve', 'supplier_invoices.post', 'supplier_invoices.cancel',
            'supplier_invoices.override_variance', 'supplier_invoices.view_cost', 'supplier_payments.view',
            'supplier_payments.create', 'supplier_payments.approve', 'supplier_payments.process',
            'supplier_payments.allocate', 'supplier_payments.reverse_allocation', 'supplier_payments.cancel',
            'supplier_credit_notes.view', 'supplier_credit_notes.create', 'supplier_credit_notes.approve',
            'supplier_credit_notes.post', 'supplier_statements.view', 'accounts_payable.aging',
        ];
        foreach ($permissions as $name) {
            Permission::updateOrCreate(['name' => $name], ['display_name' => $name]);
        }
        $grant = function (array $roles, array $names): void {
            $ids = Permission::whereIn('name', $names)->pluck('id');
            Role::whereIn('name', $roles)->get()->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
        };
        $grant(['company_owner', 'general_manager', 'branch_manager'], $permissions);
        $grant(['purchasing_manager'], array_values(array_filter($permissions, fn ($name) => ! str_starts_with($name, 'supplier_payments'))));
        $grant(['warehouse_keeper'], [
            'goods_receipts.view', 'goods_receipts.create', 'goods_receipts.update', 'goods_receipts.inspect',
            'goods_receipts.post', 'goods_receipts.cancel', 'goods_receipts.view_cost',
            'purchase_returns.view', 'purchase_returns.create', 'purchase_returns.post',
        ]);
        $grant(['accountant'], [
            'suppliers.view', 'suppliers.view_balance', 'supplier_invoices.view', 'supplier_invoices.create',
            'supplier_invoices.update', 'supplier_invoices.submit', 'supplier_invoices.approve',
            'supplier_invoices.post', 'supplier_invoices.view_cost', 'supplier_payments.view',
            'supplier_payments.create', 'supplier_payments.approve', 'supplier_payments.process',
            'supplier_payments.allocate', 'supplier_payments.reverse_allocation', 'supplier_credit_notes.view',
            'supplier_credit_notes.create', 'supplier_credit_notes.approve', 'supplier_credit_notes.post',
            'supplier_statements.view', 'accounts_payable.aging',
        ]);
        $grant(['quality_controller'], ['goods_receipts.view', 'goods_receipts.inspect']);

        Branch::where('is_active', true)->each(function (Branch $branch) {
            foreach ([
                'supplier' => '{BRANCH}-SUP-{YYYY}-',
                'purchase_requisition' => '{BRANCH}-PR-{YYYY}-',
                'purchase_order' => '{BRANCH}-PO-{YYYY}-',
                'goods_receipt' => '{BRANCH}-GR-{YYYY}-',
                'purchase_return' => '{BRANCH}-PRET-{YYYY}-',
                'supplier_invoice' => '{BRANCH}-SINV-{YYYY}-',
                'supplier_payment' => '{BRANCH}-SPAY-{YYYY}-',
                'supplier_credit_note' => '{BRANCH}-SCN-{YYYY}-',
            ] as $type => $prefix) {
                $scope = DocumentNumberService::scopeKey($branch->company_id, $branch->id, $type, now()->format('Y'));
                $sequence = DocumentSequence::firstOrNew(['scope_key' => $scope]);
                $sequence->forceFill([
                    'company_id' => $branch->company_id, 'branch_id' => $branch->id,
                    'document_type' => $type, 'prefix' => $prefix,
                    'current_number' => $sequence->exists ? $sequence->current_number : 0,
                    'padding' => 6, 'reset_period' => 'yearly', 'period_key' => now()->format('Y'),
                    'scope_key' => $scope, 'is_active' => true,
                ])->save();
            }
        });
    }
}
