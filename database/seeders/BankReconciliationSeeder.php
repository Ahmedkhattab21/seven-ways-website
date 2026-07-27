<?php

namespace Database\Seeders;

use App\Models\BankStatementImportProfile;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;

class BankReconciliationSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'treasury.bank_statements.view', 'treasury.bank_statements.view_sensitive',
            'treasury.bank_statements.import', 'treasury.bank_statements.validate',
            'treasury.bank_statements.cancel', 'treasury.bank_statements.resolve_duplicates',
            'treasury.bank_statements.ignore_lines',
            'treasury.reconciliation.view', 'treasury.reconciliation.create',
            'treasury.reconciliation.match', 'treasury.reconciliation.partial_match',
            'treasury.reconciliation.auto_match', 'treasury.reconciliation.review',
            'treasury.reconciliation.approve', 'treasury.reconciliation.complete',
            'treasury.reconciliation.reopen', 'treasury.reconciliation.export',
            'treasury.matching_rules.view', 'treasury.matching_rules.create',
            'treasury.matching_rules.update', 'treasury.matching_rules.disable',
            'treasury.bank_adjustments.view', 'treasury.bank_adjustments.create',
            'treasury.bank_adjustments.update', 'treasury.bank_adjustments.submit',
            'treasury.bank_adjustments.approve', 'treasury.bank_adjustments.post',
            'treasury.bank_adjustments.reverse',
        ];
        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(['name' => $permission], ['display_name' => $permission]);
        }
        $all = Permission::query()->whereIn('name', $permissions)->pluck('id');
        Role::query()->whereIn('name', ['system_admin', 'company_owner', 'finance_manager'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($all));
        $accountantNames = [
            'treasury.bank_statements.view', 'treasury.bank_statements.import',
            'treasury.bank_statements.validate', 'treasury.bank_statements.resolve_duplicates',
            'treasury.bank_statements.ignore_lines', 'treasury.reconciliation.view',
            'treasury.reconciliation.create', 'treasury.reconciliation.match',
            'treasury.reconciliation.partial_match', 'treasury.matching_rules.view',
            'treasury.bank_adjustments.view', 'treasury.bank_adjustments.create',
            'treasury.bank_adjustments.update', 'treasury.bank_adjustments.submit',
        ];
        Role::query()->where('name', 'accountant')->get()->each(fn (Role $role) => $role->permissions()
            ->syncWithoutDetaching(Permission::query()->whereIn('name', $accountantNames)->pluck('id')));
        $approvalNames = [
            'treasury.bank_statements.view', 'treasury.reconciliation.view',
            'treasury.reconciliation.review', 'treasury.reconciliation.approve',
            'treasury.reconciliation.complete', 'treasury.bank_adjustments.view',
            'treasury.bank_adjustments.approve',
        ];
        Role::query()->where('name', 'general_manager')->get()->each(fn (Role $role) => $role->permissions()
            ->syncWithoutDetaching(Permission::query()->whereIn('name', $approvalNames)->pluck('id')));
        Role::query()->where('name', 'branch_manager')->get()->each(fn (Role $role) => $role->permissions()
            ->syncWithoutDetaching(Permission::query()->whereIn('name', [
                'treasury.bank_statements.view', 'treasury.reconciliation.view',
            ])->pluck('id')));
        Role::query()->whereIn('name', ['cashier', 'receptionist'])->get()->each(fn (Role $role) => $role->permissions()
            ->syncWithoutDetaching(Permission::query()->whereIn('name', [
                'treasury.bank_statements.view', 'treasury.reconciliation.view',
            ])->pluck('id')));

        Company::query()->with(['users', 'branches'])->get()->each(function (Company $company) {
            $actor = $company->users->sortBy('id')->first();
            if (! $actor) {
                return;
            }
            $profile = BankStatementImportProfile::withTrashed()->firstOrNew([
                'default_scope_key' => "{$company->id}:0:csv",
            ]);
            $profile->forceFill([
                'company_id' => $company->id, 'bank_account_id' => null,
                'name' => 'Default CSV Debit/Credit', 'format' => 'csv', 'delimiter' => ',',
                'encoding' => 'UTF-8', 'date_format' => 'Y-m-d', 'decimal_separator' => '.',
                'thousands_separator' => ',', 'has_header' => true,
                'column_mapping' => [
                    'transaction_date' => 'date', 'description' => 'description',
                    'reference' => 'reference', 'debit' => 'debit', 'credit' => 'credit',
                    'running_balance' => 'balance', 'external_id' => 'external_id',
                ],
                'skip_rows' => 0, 'direction_policy' => 'credit_increases',
                'balance_tolerance' => 0, 'is_default' => true, 'is_active' => true,
                'created_by' => $actor->id, 'deleted_at' => null,
            ])->save();
            foreach ($company->branches as $branch) {
                foreach ([
                    'bank_reconciliation' => $branch->code.'-BR-{YYYY}-',
                    'bank_adjustment' => $branch->code.'-BA-{YYYY}-',
                ] as $type => $prefix) {
                    $scope = DocumentNumberService::scopeKey($company->id, $branch->id, $type, null);
                    if (! DocumentSequence::query()->where('scope_key', $scope)->exists()) {
                        $sequence = new DocumentSequence;
                        $sequence->forceFill([
                            'company_id' => $company->id, 'branch_id' => $branch->id,
                            'document_type' => $type, 'prefix' => $prefix, 'current_number' => 0,
                            'padding' => 6, 'reset_period' => 'yearly', 'period_key' => null,
                            'scope_key' => $scope, 'is_active' => true,
                        ])->save();
                    }
                }
            }
        });
    }
}
