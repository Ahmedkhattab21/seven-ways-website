<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Warehouse;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;

class StockTransferSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'stock_transfers.view', 'stock_transfers.create', 'stock_transfers.update',
            'stock_transfers.approve', 'stock_transfers.prepare', 'stock_transfers.ship',
            'stock_transfers.receive', 'stock_transfers.cancel', 'stock_transfers.reverse',
            'stock_transfers.resolve_discrepancy',
        ];
        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(['name' => $permission], ['display_name' => $permission]);
        }
        $all = Permission::query()->whereIn('name', $permissions)->pluck('id');
        Role::query()->whereIn('name', ['company_owner', 'general_manager'])->get()->each(
            fn (Role $role) => $role->permissions()->syncWithoutDetaching($all)
        );
        $branchManager = [
            'stock_transfers.view', 'stock_transfers.create', 'stock_transfers.update',
            'stock_transfers.approve', 'stock_transfers.prepare', 'stock_transfers.ship',
            'stock_transfers.receive', 'stock_transfers.cancel',
        ];
        $keeper = ['stock_transfers.view', 'stock_transfers.prepare', 'stock_transfers.ship', 'stock_transfers.receive'];
        foreach (['branch_manager' => $branchManager, 'warehouse_keeper' => $keeper, 'accountant' => ['stock_transfers.view']] as $roleName => $allowed) {
            Role::query()->where('name', $roleName)->get()->each(
                fn (Role $role) => $role->permissions()->syncWithoutDetaching(Permission::whereIn('name', $allowed)->pluck('id'))
            );
        }

        $year = now()->format('Y');
        Branch::query()->where('is_active', true)->with('company')->each(function (Branch $branch) use ($year) {
            $transit = Warehouse::withTrashed()->firstOrNew(['branch_id' => $branch->id, 'code' => 'TRANSIT']);
            $transit->forceFill([
                'company_id' => $branch->company_id, 'branch_id' => $branch->id, 'code' => 'TRANSIT',
                'name' => 'مخزون قيد النقل', 'warehouse_type' => 'transit',
                'is_main' => false, 'is_active' => true, 'is_system' => true,
                'allows_sale_issue' => false, 'allows_work_order_issue' => false,
                'allows_damaged_stock' => false, 'deleted_at' => null,
            ])->save();

            $scopeKey = DocumentNumberService::scopeKey($branch->company_id, $branch->id, 'stock_transfer', $year);
            $sequence = DocumentSequence::query()->firstOrNew(['scope_key' => $scopeKey]);
            $sequence->forceFill([
                'company_id' => $branch->company_id, 'branch_id' => $branch->id,
                'scope_key' => $scopeKey, 'document_type' => 'stock_transfer',
                'prefix' => '{BRANCH}-TRF-{YYYY}-',
                'current_number' => $sequence->exists ? $sequence->current_number : 0,
                'padding' => 6, 'reset_period' => 'yearly',
                'period_key' => $year, 'is_active' => true,
            ])->save();
        });
    }
}
