<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\QualityChecklistTemplate;
use App\Models\Role;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class QualityWarrantySeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'quality_checks.view', 'quality_checks.create', 'quality_checks.perform', 'quality_checks.pass',
            'quality_checks.fail', 'quality_checks.override', 'quality_checks.manage_templates',
            'rework_orders.view', 'rework_orders.create', 'rework_orders.approve', 'rework_orders.start',
            'rework_orders.complete', 'rework_orders.view_cost', 'work_orders.deliver',
            'vehicle_inspections.delivery', 'vehicle_inspections.delivery_photos',
            'warranties.view', 'warranties.issue', 'warranties.print', 'warranties.void',
            'warranties.verify_sensitive', 'warranty_claims.view', 'warranty_claims.create',
            'warranty_claims.inspect', 'warranty_claims.decide', 'warranty_claims.approve',
            'warranty_claims.reject', 'warranty_claims.resolve', 'warranty_claims.view_cost',
        ];
        foreach ($permissions as $name) {
            Permission::query()->updateOrCreate(['name' => $name], ['display_name' => $name]);
        }
        $grant = function (array $roles, array $names): void {
            $ids = Permission::whereIn('name', $names)->pluck('id');
            Role::whereIn('name', $roles)->get()->each(
                fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids)
            );
        };
        $grant(['company_owner', 'general_manager', 'branch_manager'], $permissions);
        $grant(['quality_controller'], [
            'quality_checks.view', 'quality_checks.create', 'quality_checks.perform',
            'quality_checks.pass', 'quality_checks.fail', 'rework_orders.view',
        ]);
        $grant(['technician'], ['rework_orders.view', 'rework_orders.start', 'rework_orders.complete']);
        $grant(['receptionist'], [
            'work_orders.deliver', 'vehicle_inspections.delivery', 'vehicle_inspections.delivery_photos',
            'warranties.view', 'warranties.print', 'warranty_claims.view', 'warranty_claims.create',
        ]);
        $grant(['accountant'], [
            'rework_orders.view', 'rework_orders.view_cost', 'warranties.view',
            'warranty_claims.view', 'warranty_claims.view_cost',
        ]);
        $grant(['sales'], ['warranties.view', 'warranties.print']);
        $grant(['warehouse_keeper'], ['rework_orders.view']);

        Branch::where('is_active', true)->each(function (Branch $branch) {
            foreach ([
                'quality_check' => '{BRANCH}-QC-{YYYY}-',
                'rework_order' => '{BRANCH}-RW-{YYYY}-',
                'warranty' => '{BRANCH}-WAR-{YYYY}-',
                'warranty_claim' => '{BRANCH}-WCL-{YYYY}-',
            ] as $type => $prefix) {
                $scope = DocumentNumberService::scopeKey($branch->company_id, $branch->id, $type, now()->format('Y'));
                $sequence = DocumentSequence::firstOrNew(['scope_key' => $scope]);
                $sequence->forceFill([
                    'company_id' => $branch->company_id, 'branch_id' => $branch->id,
                    'document_type' => $type, 'prefix' => $prefix,
                    'current_number' => $sequence->exists ? $sequence->current_number : 0,
                    'padding' => 6, 'reset_period' => 'yearly', 'period_key' => now()->format('Y'),
                    'scope_key' => $scope, 'is_active' => true,
                ])->save();
            }
        });

        Company::query()->each(function (Company $company) {
            $userId = $company->users()->orderBy('id')->value('id');
            if (! $userId) {
                return;
            }
            foreach ([
                ['general', null, 'GEN-QC', 'فحص الجودة العام'],
                ['type:ppf', 'ppf', 'PPF-QC', 'فحص جودة أفلام الحماية'],
                ['type:thermal_insulation', 'thermal_insulation', 'TINT-QC', 'فحص جودة العازل الحراري'],
            ] as [$scope, $type, $code, $name]) {
                $template = QualityChecklistTemplate::query()->firstOrCreate(
                    ['company_id' => $company->id, 'scope_key' => $scope, 'version' => 1],
                    [
                        'uuid' => (string) Str::uuid(), 'service_type' => $type, 'code' => $code,
                        'name' => $name, 'is_default' => true, 'is_active' => true,
                        'created_by' => $userId,
                    ]
                );
                foreach ($this->items() as $position => $item) {
                    $template->items()->firstOrCreate(
                        ['code' => $item[0]],
                        [
                            'name' => $item[1], 'category' => $item[2], 'check_type' => 'pass_fail',
                            'is_required' => true, 'is_critical' => $item[3],
                            'requires_photo_on_failure' => $item[3], 'sort_order' => $position,
                        ]
                    );
                }
            }
        });
    }

    private function items(): array
    {
        return [
            ['NO_BUBBLES', 'عدم وجود فقاعات', 'finish', true],
            ['NO_SCRATCHES', 'عدم وجود خدوش', 'finish', true],
            ['EDGE_ADHESION', 'ثبات الحواف', 'installation', true],
            ['CUT_ACCURACY', 'دقة القص', 'installation', false],
            ['SURFACE_CLEAN', 'نظافة السطح', 'cleanliness', false],
            ['GLASS_SAFE', 'سلامة الزجاج', 'safety', true],
            ['COLOR_MATCH', 'مطابقة اللون', 'finish', false],
            ['NO_DUST', 'عدم وجود أتربة', 'cleanliness', false],
        ];
    }
}
