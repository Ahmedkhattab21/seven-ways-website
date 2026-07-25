<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class FoundationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view' => 'عرض لوحة التحكم',
            'companies.view' => 'عرض بيانات الشركة',
            'companies.update' => 'تعديل بيانات الشركة',
            'branches.view' => 'عرض الفروع',
            'branches.create' => 'إنشاء الفروع',
            'branches.update' => 'تعديل الفروع',
            'branches.disable' => 'تعطيل الفروع',
            'users.view' => 'عرض المستخدمين',
            'users.create' => 'إنشاء المستخدمين',
            'users.update' => 'تعديل المستخدمين',
            'users.disable' => 'تعطيل المستخدمين',
            'roles.view' => 'عرض الأدوار',
            'roles.manage' => 'إدارة صلاحيات الأدوار',
        ];

        foreach ($permissions as $name => $displayName) {
            Permission::query()->updateOrCreate(['name' => $name], ['display_name' => $displayName]);
        }

        $roles = [
            'system_admin' => ['مدير النظام', 'system'],
            'company_owner' => ['مالك الشركة', 'company'],
            'general_manager' => ['المدير العام', 'company'],
            'branch_manager' => ['مدير فرع', 'branch'],
            'accountant' => ['محاسب', 'branch'],
            'sales' => ['مبيعات', 'branch'],
            'warehouse_keeper' => ['أمين مخزن', 'branch'],
            'technician' => ['فني', 'branch'],
            'quality_controller' => ['مراقب جودة', 'branch'],
            'receptionist' => ['استقبال', 'branch'],
        ];

        foreach ($roles as $name => [$displayName, $scope]) {
            $role = Role::query()->updateOrCreate(
                ['company_id' => null, 'name' => $name],
                ['display_name' => $displayName, 'scope' => $scope, 'is_system' => true, 'is_active' => true]
            );

            $allowed = match ($name) {
                'system_admin', 'company_owner', 'general_manager' => array_keys($permissions),
                'branch_manager' => [
                    'dashboard.view', 'branches.view', 'users.view', 'users.create', 'users.update', 'roles.view',
                ],
                default => ['dashboard.view'],
            };

            $role->permissions()->sync(Permission::query()->whereIn('name', $allowed)->pluck('id'));
        }
    }
}
