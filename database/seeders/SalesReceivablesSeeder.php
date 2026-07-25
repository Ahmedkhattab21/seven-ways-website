<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;

class SalesReceivablesSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'sales_invoices.view', 'sales_invoices.create', 'sales_invoices.update', 'sales_invoices.submit',
            'sales_invoices.approve', 'sales_invoices.issue', 'sales_invoices.cancel', 'sales_invoices.void',
            'sales_invoices.view_cost', 'sales_invoices.print', 'sales_invoices.direct_sale', 'sales_invoices.override_price',
            'customer_payments.view', 'customer_payments.record', 'customer_payments.approve', 'customer_payments.allocate',
            'customer_payments.reverse_allocation', 'customer_payments.cancel', 'customer_payments.print',
            'sales_credit_notes.view', 'sales_credit_notes.create', 'sales_credit_notes.approve', 'sales_credit_notes.issue',
            'sales_credit_notes.cancel', 'sales_credit_notes.print', 'customer_refunds.view', 'customer_refunds.create',
            'customer_refunds.approve', 'customer_refunds.process', 'customer_statements.view', 'accounts_receivable.aging',
        ];
        foreach ($permissions as $name) {
            Permission::updateOrCreate(['name' => $name], ['display_name' => $name]);
        }
        $grant = function (array $roles, array $names): void {
            $ids = Permission::whereIn('name', $names)->pluck('id');
            Role::whereIn('name', $roles)->get()->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
        };
        $grant(['company_owner', 'general_manager', 'branch_manager'], $permissions);
        $grant(['accountant'], $permissions);
        $grant(['sales'], ['sales_invoices.view', 'sales_invoices.create', 'sales_invoices.update', 'sales_invoices.submit', 'sales_invoices.print', 'sales_invoices.direct_sale', 'sales_credit_notes.view', 'customer_statements.view']);
        $grant(['receptionist'], ['sales_invoices.view', 'customer_payments.view', 'customer_payments.record', 'customer_payments.print', 'customer_statements.view']);
        $grant(['warehouse_keeper'], ['sales_invoices.view']);
        Branch::where('is_active', true)->each(function (Branch $branch) {
            foreach ([
                'sales_invoice' => '{BRANCH}-INV-{YYYY}-', 'customer_payment' => '{BRANCH}-PAY-{YYYY}-',
                'sales_credit_note' => '{BRANCH}-CN-{YYYY}-', 'customer_refund' => '{BRANCH}-REF-{YYYY}-',
            ] as $type => $prefix) {
                $scope = DocumentNumberService::scopeKey($branch->company_id, $branch->id, $type, now()->format('Y'));
                $sequence = DocumentSequence::firstOrNew(['scope_key' => $scope]);
                $sequence->forceFill([
                    'company_id' => $branch->company_id, 'branch_id' => $branch->id, 'document_type' => $type,
                    'prefix' => $prefix, 'current_number' => $sequence->exists ? $sequence->current_number : 0,
                    'padding' => 6, 'reset_period' => 'yearly', 'period_key' => now()->format('Y'), 'scope_key' => $scope, 'is_active' => true,
                ])->save();
            }
        });
    }
}
