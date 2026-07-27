<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class CentralWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'approvals.view' => 'عرض صندوق الاعتمادات',
            'approvals.act' => 'اتخاذ قرار اعتماد مركزي',
            'approvals.manage_workflows' => 'إدارة مسارات الاعتماد',
            'approvals.view_all_branches' => 'عرض اعتمادات كل الفروع',
            'notifications.view' => 'عرض الإشعارات',
            'notifications.generate' => 'توليد الإشعارات التشغيلية',
            'audit.view' => 'عرض سجل التدقيق',
            'audit.view_sensitive' => 'عرض تفاصيل التدقيق الحساسة',
            'audit.export' => 'تصدير سجل التدقيق',
            'delegations.view' => 'عرض تفويضات الاعتماد',
            'delegations.create' => 'إنشاء تفويض اعتماد',
            'delegations.cancel' => 'إلغاء تفويض اعتماد',
        ];
        foreach ($permissions as $name => $displayName) {
            Permission::updateOrCreate(['name' => $name], ['display_name' => $displayName]);
        }

        $rolePermissions = [
            'system_admin' => array_keys($permissions),
            'company_owner' => array_keys($permissions),
            'general_manager' => [
                'approvals.view', 'approvals.act', 'approvals.manage_workflows', 'approvals.view_all_branches',
                'notifications.view', 'audit.view', 'delegations.view', 'delegations.create', 'delegations.cancel',
            ],
            'branch_manager' => [
                'approvals.view', 'approvals.act', 'notifications.view', 'audit.view',
                'delegations.view', 'delegations.create', 'delegations.cancel',
            ],
            'accountant' => ['approvals.view', 'approvals.act', 'notifications.view'],
        ];
        foreach ($rolePermissions as $roleName => $names) {
            Role::whereNull('company_id')->where('name', $roleName)->get()->each(function (Role $role) use ($names) {
                $role->permissions()->syncWithoutDetaching(Permission::whereIn('name', $names)->pluck('id'));
            });
        }

        foreach ([
            ['purchasing', 'PurchaseRequisition', 'purchase_requisitions.approve'],
            ['purchasing', 'PurchaseOrder', 'purchase_orders.approve'],
            ['treasury', 'TreasuryTransfer', 'treasury.transfers.approve'],
        ] as [$module, $type, $permission]) {
            $workflow = ApprovalWorkflow::updateOrCreate(
                ['company_id' => null, 'branch_id' => null, 'module' => $module, 'document_type' => $type, 'version' => 1],
                ['is_active' => true]
            );
            ApprovalWorkflowStep::updateOrCreate(
                ['workflow_id' => $workflow->id, 'step_order' => 1],
                ['required_permission' => $permission, 'minimum_approvals' => 1, 'enforce_sod' => true]
            );
        }
    }
}
