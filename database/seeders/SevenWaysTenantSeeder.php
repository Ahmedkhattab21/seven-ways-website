<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SevenWaysTenantSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('SevenWaysTenantSeeder is restricted to local and testing environments.');
        }

        $company = Company::query()->firstOrCreate(
            ['name' => 'Seven Ways'],
            ['country_code' => 'EG', 'currency_code' => 'EGP', 'timezone' => 'Africa/Cairo', 'is_active' => true]
        );

        $branch = Branch::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'MAIN'],
            ['name' => 'الفرع الرئيسي', 'is_main' => true, 'is_active' => true]
        );

        $branch->settings()->firstOrCreate([], [
            'invoice_prefix' => 'INV',
            'quotation_prefix' => 'QUO',
            'work_order_prefix' => 'WO',
            'warranty_prefix' => 'WAR',
        ]);

        $email = env('SEED_ADMIN_EMAIL');
        $password = env('SEED_ADMIN_PASSWORD');

        if (! $email && ! $password) {
            return;
        }

        if (! $email || ! $password || mb_strlen($password) < 12) {
            throw new RuntimeException('SEED_ADMIN_EMAIL and a password of at least 12 characters are both required.');
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('SEED_ADMIN_NAME', 'Seven Ways Admin'),
                'password' => Hash::make($password),
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'status' => 'active',
            ]
        );

        $user->roles()->syncWithoutDetaching(Role::query()->where('name', 'company_owner')->pluck('id'));
        $user->accessibleBranches()->syncWithoutDetaching([
            $branch->id => ['is_default' => true, 'can_view' => true, 'can_create' => true, 'can_update' => true, 'can_approve' => true],
        ]);
    }
}
