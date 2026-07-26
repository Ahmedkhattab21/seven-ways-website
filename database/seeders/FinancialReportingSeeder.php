<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\CashFlowMapping;
use App\Models\Company;
use App\Models\FinancialReportDefinition;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class FinancialReportingSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'accounting.general_ledger.view', 'accounting.general_ledger.export', 'accounting.general_ledger.view_sensitive',
            'accounting.general_journal.view', 'accounting.general_journal.export',
            'accounting.trial_balance.view', 'accounting.trial_balance.export',
            'accounting.income_statement.view', 'accounting.income_statement.export',
            'accounting.balance_sheet.view', 'accounting.balance_sheet.export',
            'accounting.cash_flow.view', 'accounting.cash_flow.export',
            'accounting.cost_center_reports.view', 'accounting.branch_reports.view',
            'accounting.reconciliation.customers', 'accounting.reconciliation.suppliers',
            'accounting.reconciliation.inventory', 'accounting.reconciliation.tax',
            'accounting.unposted_sources.view', 'accounting.financial_reports.manage_definitions',
            'accounting.financial_reports.manage_mappings', 'accounting.financial_reports.view_sensitive',
        ];
        foreach ($permissions as $name) {
            Permission::query()->updateOrCreate(['name' => $name], ['display_name' => $name]);
        }
        $all = Permission::query()->whereIn('name', $permissions)->pluck('id');
        Role::query()->whereIn('name', ['system_admin', 'company_owner', 'finance_manager', 'accountant'])->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($all));
        $statements = Permission::query()->whereIn('name', [
            'accounting.trial_balance.view', 'accounting.income_statement.view',
            'accounting.balance_sheet.view', 'accounting.cash_flow.view',
        ])->pluck('id');
        Role::query()->where('name', 'general_manager')->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($statements));
        $branch = Permission::query()->whereIn('name', [
            'accounting.general_ledger.view', 'accounting.trial_balance.view',
            'accounting.income_statement.view', 'accounting.balance_sheet.view',
            'accounting.cost_center_reports.view', 'accounting.branch_reports.view',
        ])->pluck('id');
        Role::query()->where('name', 'branch_manager')->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($branch));

        Company::query()->with('users')->get()->each(fn (Company $company) => $this->company($company));
    }

    private function company(Company $company): void
    {
        $actor = $company->users->sortBy('id')->first();
        if (! $actor) {
            return;
        }
        foreach ([
            'INCOME-STATEMENT' => ['قائمة الدخل الأساسية', 'income_statement'],
            'BALANCE-SHEET' => ['الميزانية العمومية الأساسية', 'balance_sheet'],
            'CASH-FLOW' => ['التدفقات النقدية — Direct Foundation', 'cash_flow'],
        ] as $code => [$name, $type]) {
            $definition = FinancialReportDefinition::query()->firstOrNew(['company_id' => $company->id, 'code' => $code]);
            $definition->forceFill([
                'company_id' => $company->id, 'code' => $code, 'name_ar' => $name,
                'report_type' => $type, 'is_system' => true, 'is_active' => true,
                'created_by' => $definition->created_by ?: $actor->id,
            ])->save();
            foreach ($this->sections($type) as $order => [$sectionCode, $sectionName, $sectionType, $isTotal]) {
                $definition->sections()->updateOrCreate(['code' => $sectionCode], [
                    'name_ar' => $sectionName, 'section_type' => $sectionType,
                    'sign_multiplier' => 1, 'sort_order' => $order + 1, 'is_total' => $isTotal,
                ]);
            }
        }
        Account::query()->where('company_id', $company->id)->where('is_posting', true)
            ->where(fn ($q) => $q->where('is_cash_account', false)->where('is_bank_account', false))
            ->with('type')->get()->each(function (Account $account) use ($company, $actor) {
                $category = match ($account->type?->classification) {
                    'revenue', 'expense', 'liability' => 'operating',
                    'equity' => 'financing',
                    default => str_starts_with($account->account_code, '12') ? 'investing' : 'operating',
                };
                $mapping = CashFlowMapping::query()->firstOrNew(['company_id' => $company->id, 'account_id' => $account->id]);
                $mapping->forceFill([
                    'company_id' => $company->id, 'account_id' => $account->id,
                    'cash_flow_category' => $category, 'cash_flow_line' => ucfirst($category),
                    'is_active' => true, 'created_by' => $mapping->created_by ?: $actor->id,
                ])->save();
            });
    }

    private function sections(string $type): array
    {
        return match ($type) {
            'income_statement' => [
                ['REVENUE', 'الإيرادات', 'detail', false], ['COGS', 'تكلفة المبيعات', 'detail', false],
                ['GROSS-PROFIT', 'مجمل الربح', 'calculated', true], ['EXPENSES', 'المصروفات', 'detail', false],
                ['NET-PROFIT', 'صافي الربح', 'calculated', true],
            ],
            'balance_sheet' => [
                ['ASSETS', 'الأصول', 'detail', true], ['LIABILITIES', 'الالتزامات', 'detail', true],
                ['EQUITY', 'حقوق الملكية', 'detail', true], ['CURRENT-PROFIT', 'ربح الفترة الحالي', 'calculated', false],
            ],
            default => [
                ['OPERATING', 'أنشطة التشغيل', 'detail', true], ['INVESTING', 'أنشطة الاستثمار', 'detail', true],
                ['FINANCING', 'أنشطة التمويل', 'detail', true], ['UNCLASSIFIED', 'غير مصنف', 'detail', true],
            ],
        };
    }
}
