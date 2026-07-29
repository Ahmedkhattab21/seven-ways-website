<?php

namespace Database\Seeders;

use App\Models\BranchService;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'service_categories.view', 'service_categories.manage',
            'services.view', 'services.create', 'services.update', 'services.disable',
            'services.manage_branch_availability', 'services.manage_prices', 'services.manage_materials',
            'services.manage_roll_profiles', 'services.manage_skills', 'services.manage_commissions', 'services.view_cost',
            'service_packages.view', 'service_packages.create', 'service_packages.update',
            'service_packages.disable', 'service_packages.manage_prices',
            'promotions.view', 'promotions.manage',
        ];
        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(['name' => $permission], ['display_name' => $permission]);
        }
        $grant = function (array $roles, array $allowed): void {
            $ids = Permission::query()->whereIn('name', $allowed)->pluck('id');
            Role::query()->whereIn('name', $roles)->get()->each(
                fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids)
            );
        };
        $grant(['company_owner', 'general_manager'], $permissions);
        $grant(['branch_manager'], [
            'service_categories.view', 'services.view', 'services.manage_branch_availability',
            'services.manage_prices', 'services.manage_skills', 'service_packages.view',
            'service_packages.manage_prices', 'promotions.view',
        ]);
        $grant(['sales', 'receptionist'], ['service_categories.view', 'services.view', 'service_packages.view', 'promotions.view']);
        $grant(['warehouse_keeper'], ['service_categories.view', 'services.view', 'services.manage_materials']);
        $grant(['accountant'], [
            'service_categories.view', 'services.view', 'services.view_cost', 'service_packages.view', 'promotions.view',
        ]);

        Company::query()->where('is_active', true)->each(function (Company $company) {
            foreach ([
                'PPF' => 'أفلام حماية PPF',
                'THERMAL' => 'العازل الحراري والتظليل',
                'GLASS' => 'حماية الزجاج',
                'INTERIOR' => 'الحماية الداخلية',
                'DETAILING' => 'التلميع والتجهيز',
                'REMOVAL' => 'الإزالة والصيانة',
            ] as $code => $name) {
                $category = ServiceCategory::withTrashed()->firstOrNew(['company_id' => $company->id, 'code' => $code]);
                $category->forceFill([
                    'company_id' => $company->id, 'code' => $code, 'name' => $name,
                    'sort_order' => 0, 'is_active' => true, 'deleted_at' => null,
                ])->save();
            }

            $definitions = [
                ['PPF-FULL', 'حماية السيارة كاملة', 'PPF', 'ppf'],
                ['PPF-FRONT', 'حماية الواجهة الأمامية', 'PPF', 'ppf'],
                ['PPF-HOOD', 'حماية الكبوت', 'PPF', 'ppf'],
                ['PPF-BUMPER', 'حماية الصدام', 'PPF', 'ppf'],
                ['PPF-LIGHTS', 'حماية المصابيح', 'GLASS', 'glass_protection'],
                ['PPF-HANDLES', 'حماية مقابض الأبواب', 'PPF', 'ppf'],
                ['TINT-FULL', 'عازل حراري كامل', 'THERMAL', 'thermal_insulation'],
                ['TINT-WINDSHIELD', 'عازل الزجاج الأمامي', 'THERMAL', 'tint'],
                ['REMOVE-FILM', 'إزالة فيلم قديم', 'REMOVAL', 'removal'],
                ['INTERIOR-SCREEN', 'حماية الشاشة والديكورات', 'INTERIOR', 'interior_protection'],
            ];
            foreach ($definitions as [$code, $name, $categoryCode, $type]) {
                $category = ServiceCategory::where('company_id', $company->id)->where('code', $categoryCode)->firstOrFail();
                $service = Service::withTrashed()->firstOrNew(['company_id' => $company->id, 'code' => $code]);
                $service->forceFill([
                    'company_id' => $company->id, 'service_category_id' => $category->id,
                    'code' => $code, 'name' => $name, 'service_type' => $type,
                    'pricing_type' => 'custom_quote', 'default_duration_minutes' => 60,
                    'requires_vehicle' => true, 'requires_inspection' => false,
                    'requires_quality_check' => true, 'allows_multiple_technicians' => false,
                    'is_package_only' => false, 'is_active' => true, 'deleted_at' => null,
                ])->save();
            }

            $branches = $company->branches()->where('is_active', true)->get();
            Service::query()
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->each(function (Service $service) use ($branches) {
                    foreach ($branches as $branch) {
                        $availability = BranchService::query()->firstOrNew([
                            'branch_id' => $branch->id,
                            'service_id' => $service->id,
                        ]);
                        if (! $availability->exists) {
                            $availability->forceFill([
                                'company_id' => $service->company_id,
                                'branch_id' => $branch->id,
                                'service_id' => $service->id,
                                'is_available' => true,
                                'booking_enabled' => true,
                                'requires_approval' => false,
                                'default_duration_minutes' => $service->default_duration_minutes,
                                'is_active' => true,
                            ])->save();
                        }
                    }
                });

            foreach (['service' => 'SRV-', 'service_package' => 'PKG-', 'promotion' => 'PRM-'] as $type => $prefix) {
                $scopeKey = DocumentNumberService::scopeKey($company->id, null, $type, null);
                $sequence = DocumentSequence::query()->firstOrNew(['scope_key' => $scopeKey]);
                $sequence->forceFill([
                    'company_id' => $company->id, 'branch_id' => null, 'document_type' => $type,
                    'prefix' => $prefix, 'current_number' => $sequence->exists ? $sequence->current_number : 0,
                    'padding' => 6, 'reset_period' => 'never', 'period_key' => null,
                    'scope_key' => $scopeKey, 'is_active' => true,
                ])->save();
            }
        });
    }
}
