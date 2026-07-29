<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class EmployeeManagementSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'employees.view' => 'عرض الموظفين والفنيين',
            'employees.create' => 'إضافة الموظفين والفنيين',
            'employees.update' => 'تعديل الموظفين والفنيين',
            'employees.disable' => 'تعطيل الموظفين والفنيين',
            'employees.manage_skills' => 'إدارة مهارات الفنيين',
        ];

        foreach ($permissions as $name => $displayName) {
            Permission::query()->updateOrCreate(['name' => $name], ['display_name' => $displayName]);
        }

        $permissionIds = Permission::query()->whereIn('name', array_keys($permissions))->pluck('id');
        Role::query()
            ->whereIn('name', ['system_admin', 'company_owner', 'general_manager', 'branch_manager'])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($permissionIds));
    }
}
