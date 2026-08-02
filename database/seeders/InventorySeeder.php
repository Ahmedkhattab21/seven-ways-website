<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Warehouse;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'products.view', 'products.create', 'products.update', 'products.disable',
            'product_categories.manage', 'product_brands.manage',
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.disable',
            'inventory.view', 'inventory.view_cost', 'inventory.opening', 'inventory.adjust',
            'inventory.count', 'inventory.post', 'inventory.reverse',
            'rolls.view', 'rolls.create', 'rolls.consume', 'rolls.waste',
            'rolls.manage_scraps', 'rolls.change_status',
            'inventory_reservations.view', 'inventory_reservations.manage',
        ];
        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(['name' => $permission], ['display_name' => $permission]);
        }
        $all = Permission::query()->whereIn('name', $permissions)->pluck('id');
        foreach (Role::query()->whereIn('name', ['company_owner', 'general_manager'])->get() as $role) {
            $role->permissions()->syncWithoutDetaching($all);
        }
        Role::query()->where('name', 'branch_manager')->get()->each(function (Role $role) use ($permissions): void {
            $role->permissions()->detach(Permission::query()->where('name', 'inventory.post')->value('id'));
            $role->permissions()->syncWithoutDetaching(
                Permission::query()->whereIn('name', $permissions)->where('name', '!=', 'inventory.post')->pluck('id')
            );
        });
        $keeper = [
            'products.view', 'warehouses.view', 'inventory.view', 'inventory.opening', 'inventory.adjust',
            'inventory.count', 'inventory.post', 'rolls.view', 'rolls.create', 'rolls.consume',
            'rolls.waste', 'rolls.manage_scraps', 'rolls.change_status',
            'inventory_reservations.view', 'inventory_reservations.manage',
        ];
        Role::query()->where('name', 'warehouse_keeper')->get()->each(
            fn (Role $role) => $role->permissions()->syncWithoutDetaching(Permission::whereIn('name', $keeper)->pluck('id'))
        );
        foreach (['accountant' => ['inventory.view', 'inventory.view_cost'], 'sales' => ['inventory.view']] as $roleName => $allowed) {
            Role::query()->where('name', $roleName)->get()->each(
                fn (Role $role) => $role->permissions()->syncWithoutDetaching(Permission::whereIn('name', $allowed)->pluck('id'))
            );
        }

        $company = Company::query()->where('name', 'Seven Ways')->first();
        if (! $company) {
            return;
        }
        foreach ([
            'PPF' => 'أفلام حماية PPF', 'THERMAL' => 'عازل حراري', 'TINT' => 'تظليل',
            'INSTALL' => 'مواد تركيب', 'TOOLS' => 'أدوات وإكسسوارات',
        ] as $code => $name) {
            ProductCategory::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'sort_order' => 0, 'is_active' => true]
            );
        }
        $year = now()->format('Y');
        $types = [
            'product' => 'PRD-', 'stock_movement' => '{BRANCH}-STK-{YYYY}-',
            'stock_opening' => '{BRANCH}-OPEN-{YYYY}-', 'stock_adjustment' => '{BRANCH}-ADJ-{YYYY}-',
            'inventory_count' => '{BRANCH}-COUNT-{YYYY}-', 'roll' => '{BRANCH}-ROLL-',
            'roll_scrap' => '{BRANCH}-SCRAP-',
        ];
        foreach ($company->branches()->where('is_active', true)->get() as $branch) {
            Warehouse::query()->updateOrCreate(
                ['branch_id' => $branch->id, 'code' => 'MAIN'],
                [
                    'company_id' => $company->id, 'name' => 'المخزن الرئيسي',
                    'warehouse_type' => 'main', 'is_main' => true, 'is_active' => true,
                    'allows_sale_issue' => true, 'allows_work_order_issue' => true,
                ]
            );
            foreach ($types as $type => $prefix) {
                $reset = in_array($type, ['product', 'roll', 'roll_scrap'], true) ? 'never' : 'yearly';
                $period = $reset === 'yearly' ? $year : null;
                DocumentSequence::query()->updateOrCreate(
                    ['scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, $type, $period)],
                    [
                        'company_id' => $company->id, 'branch_id' => $branch->id, 'document_type' => $type,
                        'prefix' => $prefix, 'current_number' => 0, 'padding' => 6,
                        'reset_period' => $reset, 'period_key' => $period, 'is_active' => true,
                    ]
                );
            }
        }
    }
}
