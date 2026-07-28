<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AnalyticsReportingSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboards.executive.view' => 'عرض لوحة المؤشرات التنفيذية',
            'dashboards.branch.view' => 'عرض لوحة مؤشرات الفروع',
            'reports.financial.view' => 'عرض التقارير المالية',
            'reports.sales.view' => 'عرض تقارير المبيعات',
            'reports.purchases.view' => 'عرض تقارير المشتريات',
            'reports.inventory.view' => 'عرض تقارير المخزون',
            'reports.receivables.view' => 'عرض تقارير العملاء والتحصيلات',
            'reports.payables.view' => 'عرض تقارير الموردين والمدفوعات',
            'reports.treasury.view' => 'عرض تقارير الخزينة والبنوك',
            'reports.employee_finance.view' => 'عرض تقارير مالية الموظفين',
            'reports.approvals.view' => 'عرض تحليلات الاعتمادات',
            'reports.audit.view' => 'عرض تقرير التدقيق الموحد',
            'reports.export' => 'تصدير التقارير',
            'reports.export_sensitive' => 'تصدير التقارير الحساسة',
            'reports.view_all_branches' => 'عرض تقارير كل الفروع',
        ];
        foreach ($permissions as $name => $displayName) {
            Permission::query()->updateOrCreate(['name' => $name], ['display_name' => $displayName]);
        }

        $rolePermissions = [
            'system_admin' => array_keys($permissions),
            'company_owner' => array_keys($permissions),
            'general_manager' => array_values(array_diff(array_keys($permissions), ['reports.export_sensitive'])),
            'branch_manager' => [
                'dashboards.branch.view', 'reports.sales.view', 'reports.purchases.view',
                'reports.inventory.view', 'reports.receivables.view', 'reports.payables.view',
                'reports.treasury.view', 'reports.employee_finance.view',
                'reports.approvals.view', 'reports.export',
            ],
            'accountant' => [
                'dashboards.branch.view', 'reports.financial.view', 'reports.receivables.view',
                'reports.payables.view', 'reports.treasury.view',
                'reports.employee_finance.view', 'reports.export',
            ],
            'sales' => ['dashboards.branch.view', 'reports.sales.view', 'reports.receivables.view'],
            'warehouse_keeper' => ['dashboards.branch.view', 'reports.inventory.view'],
        ];
        foreach ($rolePermissions as $roleName => $names) {
            $ids = Permission::query()->whereIn('name', $names)->pluck('id');
            Role::query()->whereNull('company_id')->where('name', $roleName)->get()
                ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
        }
    }
}
