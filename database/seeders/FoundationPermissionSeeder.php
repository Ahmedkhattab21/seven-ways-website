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
            'settings.view' => 'عرض الإعدادات العامة',
            'settings.manage' => 'إدارة الإعدادات العامة',
            'taxes.view' => 'عرض الضرائب',
            'taxes.manage' => 'إدارة الضرائب',
            'units.view' => 'عرض الوحدات',
            'units.manage' => 'إدارة الوحدات',
            'payment_methods.view' => 'عرض طرق الدفع',
            'payment_methods.manage' => 'إدارة طرق الدفع',
            'vehicle_references.view' => 'عرض مراجع السيارات',
            'vehicle_references.manage' => 'إدارة مراجع السيارات',
            'fiscal_years.view' => 'عرض السنوات المالية',
            'fiscal_years.manage' => 'إدارة السنوات المالية',
            'document_sequences.view' => 'عرض تسلسل المستندات',
            'document_sequences.manage' => 'إدارة تسلسل المستندات',
            'branch_settings.view' => 'عرض إعدادات الفروع',
            'branch_settings.manage' => 'إدارة إعدادات الفروع',
            'customers.view' => 'عرض العملاء',
            'customers.create' => 'إنشاء العملاء',
            'customers.update' => 'تحديث العملاء',
            'customers.disable' => 'تعطيل العملاء',
            'customers.manage_contacts' => 'إدارة جهات اتصال العملاء',
            'customers.manage_addresses' => 'إدارة عناوين العملاء',
            'customers.manage_notes' => 'إدارة ملاحظات العملاء',
            'customers.manage_attachments' => 'إدارة مرفقات العملاء',
            'vehicles.view' => 'عرض السيارات',
            'vehicles.create' => 'إنشاء السيارات',
            'vehicles.update' => 'تحديث السيارات',
            'vehicles.transfer_ownership' => 'نقل ملكية السيارات',
            'vehicles.manage_attachments' => 'إدارة مرفقات السيارات',
            'leads.view' => 'عرض العملاء المحتملين',
            'leads.create' => 'إنشاء العملاء المحتملين',
            'leads.update' => 'تحديث العملاء المحتملين',
            'leads.assign' => 'تعيين العملاء المحتملين',
            'leads.follow_up' => 'متابعة العملاء المحتملين',
            'leads.convert' => 'تحويل العملاء المحتملين',
            'leads.close' => 'إغلاق العملاء المحتملين',
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
                    'dashboard.view', 'branches.view', 'users.view', 'users.create', 'users.update',
                    'roles.view', 'settings.view', 'taxes.view', 'units.view',
                    'payment_methods.view', 'vehicle_references.view', 'branch_settings.view',
                    'branch_settings.manage',
                    'customers.view', 'customers.create', 'customers.update', 'customers.disable',
                    'customers.manage_contacts', 'customers.manage_addresses', 'customers.manage_notes',
                    'customers.manage_attachments', 'vehicles.view', 'vehicles.create', 'vehicles.update',
                    'vehicles.transfer_ownership', 'vehicles.manage_attachments', 'leads.view', 'leads.create',
                    'leads.update', 'leads.assign', 'leads.follow_up', 'leads.convert', 'leads.close',
                ],
                'accountant' => [
                    'dashboard.view', 'settings.view', 'taxes.view', 'taxes.manage',
                    'units.view', 'payment_methods.view', 'payment_methods.manage',
                    'fiscal_years.view', 'fiscal_years.manage', 'document_sequences.view',
                    'document_sequences.manage', 'branch_settings.view', 'customers.view',
                ],
                'sales' => [
                    'dashboard.view', 'customers.view', 'customers.create', 'customers.update',
                    'customers.manage_contacts', 'customers.manage_addresses', 'customers.manage_notes',
                    'vehicles.view', 'vehicles.create', 'vehicles.update', 'leads.view', 'leads.create',
                    'leads.update', 'leads.assign', 'leads.follow_up', 'leads.convert', 'leads.close',
                ],
                'receptionist' => [
                    'dashboard.view', 'customers.view', 'customers.create', 'customers.update',
                    'customers.manage_contacts', 'customers.manage_addresses', 'vehicles.view',
                    'vehicles.create', 'vehicles.update', 'leads.view', 'leads.create', 'leads.update',
                    'leads.follow_up',
                ],
                default => ['dashboard.view'],
            };

            $role->permissions()->sync(Permission::query()->whereIn('name', $allowed)->pluck('id'));
        }
    }
}
