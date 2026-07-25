<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;

class WorkOrderSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'work_orders' => ['view', 'create', 'update', 'cancel', 'assign_technicians', 'start', 'pause', 'complete', 'reopen', 'view_cost'],
            'vehicle_inspections' => ['view', 'create', 'update', 'complete', 'manage_photos'],
            'work_order_materials' => ['view', 'reserve', 'issue', 'consume_roll', 'consume_scrap', 'return', 'record_waste', 'approve_excess_waste'],
        ];
        $permissions = collect($groups)->flatMap(fn ($actions, $group) => collect($actions)->map(fn ($action) => "{$group}.{$action}"))->all();
        foreach ($permissions as $name) {
            Permission::query()->updateOrCreate(['name' => $name], ['display_name' => $name]);
        }
        $grant = function (array $roles, array $names): void {
            $ids = Permission::whereIn('name', $names)->pluck('id');
            Role::whereIn('name', $roles)->get()->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
        };
        $grant(['company_owner', 'general_manager', 'branch_manager'], $permissions);
        $grant(['receptionist'], ['work_orders.view', 'work_orders.create', 'vehicle_inspections.view', 'vehicle_inspections.create', 'vehicle_inspections.update', 'vehicle_inspections.complete', 'vehicle_inspections.manage_photos']);
        $grant(['warehouse_keeper'], ['work_orders.view', 'work_order_materials.view', 'work_order_materials.reserve', 'work_order_materials.issue', 'work_order_materials.consume_roll', 'work_order_materials.consume_scrap', 'work_order_materials.return', 'work_order_materials.record_waste']);
        $grant(['technician'], ['work_orders.view', 'work_orders.start', 'work_orders.pause', 'work_orders.complete', 'vehicle_inspections.view', 'work_order_materials.view', 'work_order_materials.record_waste']);
        $grant(['quality_controller'], ['work_orders.view', 'vehicle_inspections.view', 'work_order_materials.view']);
        $grant(['accountant'], ['work_orders.view', 'work_orders.view_cost', 'work_order_materials.view']);
        $grant(['sales'], ['work_orders.view']);

        Branch::where('is_active', true)->each(function (Branch $branch) {
            foreach (['work_order' => '{BRANCH}-WO-{YYYY}-', 'vehicle_inspection' => '{BRANCH}-VI-{YYYY}-', 'work_order_waste' => '{BRANCH}-WW-{YYYY}-'] as $type => $prefix) {
                $scope = DocumentNumberService::scopeKey($branch->company_id, $branch->id, $type, now()->format('Y'));
                $sequence = DocumentSequence::firstOrNew(['scope_key' => $scope]);
                $sequence->forceFill([
                    'company_id' => $branch->company_id, 'branch_id' => $branch->id, 'document_type' => $type,
                    'prefix' => $prefix, 'current_number' => $sequence->exists ? $sequence->current_number : 0,
                    'padding' => 6, 'reset_period' => 'yearly', 'period_key' => now()->format('Y'),
                    'scope_key' => $scope, 'is_active' => true,
                ])->save();
            }
        });
    }
}
