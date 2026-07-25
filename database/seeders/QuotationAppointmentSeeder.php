<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;

class QuotationAppointmentSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'quotations.view', 'quotations.create', 'quotations.update', 'quotations.submit', 'quotations.approve',
            'quotations.send', 'quotations.accept', 'quotations.reject', 'quotations.cancel',
            'quotations.create_version', 'quotations.manual_price', 'quotations.override_minimum_price',
            'quotations.view_cost', 'quotations.print', 'appointments.view', 'appointments.create',
            'appointments.update', 'appointments.confirm', 'appointments.assign', 'appointments.check_in',
            'appointments.cancel', 'appointments.mark_no_show', 'appointments.calendar',
            'appointment_deposits.view', 'appointment_deposits.record', 'appointment_deposits.cancel',
            'appointment_deposits.refund_status',
        ];
        foreach ($permissions as $name) {
            Permission::query()->updateOrCreate(['name' => $name], ['display_name' => $name]);
        }
        $grant = function (array $roles, array $names): void {
            $ids = Permission::query()->whereIn('name', $names)->pluck('id');
            Role::query()->whereIn('name', $roles)->get()->each(
                fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids)
            );
        };
        $grant(['company_owner', 'general_manager', 'branch_manager'], $permissions);
        $grant(['sales'], [
            'quotations.view', 'quotations.create', 'quotations.update', 'quotations.submit', 'quotations.send',
            'quotations.accept', 'quotations.reject', 'quotations.cancel', 'quotations.create_version',
            'quotations.manual_price', 'quotations.print', 'appointments.view', 'appointments.create',
            'appointments.update', 'appointments.calendar',
        ]);
        $grant(['receptionist'], [
            'quotations.view', 'appointments.view', 'appointments.create', 'appointments.update',
            'appointments.confirm', 'appointments.assign', 'appointments.check_in', 'appointments.cancel',
            'appointments.mark_no_show', 'appointments.calendar', 'appointment_deposits.view',
            'appointment_deposits.record', 'appointment_deposits.cancel',
        ]);
        $grant(['accountant'], [
            'quotations.view', 'quotations.view_cost', 'quotations.print', 'appointments.view',
            'appointment_deposits.view', 'appointment_deposits.refund_status',
        ]);

        Branch::query()->where('is_active', true)->each(function (Branch $branch) {
            foreach ([
                'quotation' => '{BRANCH}-QT-{YYYY}-', 'appointment' => '{BRANCH}-APT-{YYYY}-',
                'appointment_deposit' => '{BRANCH}-DEP-{YYYY}-',
            ] as $type => $prefix) {
                $scope = DocumentNumberService::scopeKey($branch->company_id, $branch->id, $type, now()->format('Y'));
                $sequence = DocumentSequence::query()->firstOrNew(['scope_key' => $scope]);
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
