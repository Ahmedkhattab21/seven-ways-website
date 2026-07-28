<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class BootstrapAccessSeeder extends Seeder
{
    private const COMPANY_NAME = 'Seven Ways';

    private const SYSTEM_ADMIN_EMAIL = 'system.admin@sevenways.test';

    private const OWNER_EMAIL = 'owner@sevenways.test';

    private const DEFAULT_PASSWORD = 'Test@123456';

    public function run(): void
    {
        $this->assertEnvironmentIsSafe();

        $this->call(FoundationPermissionSeeder::class);

        $egp = Currency::query()->where('code', 'EGP')->where('is_active', true)->first();
        if (! $egp) {
            throw new RuntimeException('Active EGP is required. Run ProductionReferenceSeeder first.');
        }

        DB::transaction(function () use ($egp): void {
            $credentials = $this->credentials();
            $company = Company::query()->firstOrCreate(
                ['name' => $credentials['company_name']],
                [
                    'legal_name' => self::COMPANY_NAME,
                    'country_code' => 'EG',
                    'currency_code' => 'EGP',
                    'currency_id' => $egp->id,
                    'timezone' => 'Africa/Cairo',
                    'default_language' => 'ar',
                    'ui_direction' => 'rtl',
                    'money_decimal_places' => 2,
                    'is_active' => true,
                ]
            );

            $company->forceFill([
                'country_code' => 'EG',
                'currency_code' => 'EGP',
                'currency_id' => $egp->id,
                'timezone' => 'Africa/Cairo',
                'default_language' => 'ar',
                'ui_direction' => 'rtl',
                'money_decimal_places' => 2,
                'is_active' => true,
            ])->save();

            $systemRole = Role::query()->whereNull('company_id')->where('name', 'system_admin')->firstOrFail();
            $ownerTemplate = Role::query()->whereNull('company_id')->where('name', 'company_owner')->firstOrFail();
            $ownerRole = Role::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => 'company_owner'],
                [
                    'display_name' => $ownerTemplate->display_name,
                    'scope' => 'company',
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
            $ownerRole->permissions()->syncWithoutDetaching($ownerTemplate->permissions()->pluck('permissions.id'));

            $this->upsertUser(
                $credentials['system_admin_email'],
                $credentials['system_admin_name'],
                null,
                $systemRole,
                null,
                $credentials['system_admin_password']
            );
            $this->upsertUser(
                $credentials['owner_email'],
                $credentials['owner_name'],
                $company,
                $ownerRole,
                null,
                $credentials['owner_password']
            );
        });
    }

    private function upsertUser(
        string $email,
        string $name,
        ?Company $company,
        Role $role,
        ?int $branchId,
        string $password
    ): void {
        $user = User::query()->firstOrNew(['email' => $email]);
        if ($user->exists && $user->company_id && $company && (int) $user->company_id !== (int) $company->id) {
            throw new RuntimeException("Bootstrap email {$email} belongs to another company.");
        }

        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'company_id' => $company?->id,
            'branch_id' => $branchId,
            'status' => 'active',
            'password' => $user->exists && $user->password ? $user->password : Hash::make($password),
            'email_verified_at' => $user->email_verified_at ?: now(),
        ])->save();
        $user->roles()->sync([$role->id]);
    }

    private function credentials(): array
    {
        if (app()->environment('production')) {
            return [
                'system_admin_name' => (string) env('BOOTSTRAP_SYSTEM_ADMIN_NAME', 'System Administrator'),
                'system_admin_email' => (string) env('BOOTSTRAP_SYSTEM_ADMIN_EMAIL'),
                'system_admin_password' => (string) env('BOOTSTRAP_SYSTEM_ADMIN_PASSWORD'),
                'company_name' => (string) env('BOOTSTRAP_COMPANY_NAME', self::COMPANY_NAME),
                'owner_name' => (string) env('BOOTSTRAP_OWNER_NAME', 'Seven Ways Owner'),
                'owner_email' => (string) env('BOOTSTRAP_OWNER_EMAIL'),
                'owner_password' => (string) env('BOOTSTRAP_OWNER_PASSWORD'),
            ];
        }

        return [
            'system_admin_name' => 'System Administrator',
            'system_admin_email' => self::SYSTEM_ADMIN_EMAIL,
            'system_admin_password' => self::DEFAULT_PASSWORD,
            'company_name' => self::COMPANY_NAME,
            'owner_name' => 'Seven Ways Owner',
            'owner_email' => self::OWNER_EMAIL,
            'owner_password' => self::DEFAULT_PASSWORD,
        ];
    }

    private function assertEnvironmentIsSafe(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        if (! in_array('--force', $_SERVER['argv'] ?? [], true)) {
            throw new RuntimeException('BootstrapAccessSeeder in production requires --force.');
        }

        foreach (['BOOTSTRAP_SYSTEM_ADMIN_EMAIL', 'BOOTSTRAP_SYSTEM_ADMIN_PASSWORD', 'BOOTSTRAP_OWNER_EMAIL', 'BOOTSTRAP_OWNER_PASSWORD'] as $key) {
            if (! is_string(env($key)) || trim((string) env($key)) === '') {
                throw new RuntimeException("Missing required production environment value: {$key}.");
            }
        }

        foreach (['BOOTSTRAP_SYSTEM_ADMIN_EMAIL', 'BOOTSTRAP_OWNER_EMAIL'] as $key) {
            if (str_ends_with(strtolower((string) env($key)), '.test')) {
                throw new RuntimeException("Production bootstrap email cannot use .test: {$key}.");
            }
        }

        if (in_array(self::DEFAULT_PASSWORD, [env('BOOTSTRAP_SYSTEM_ADMIN_PASSWORD'), env('BOOTSTRAP_OWNER_PASSWORD')], true)) {
            throw new RuntimeException('Production bootstrap passwords must not use test defaults.');
        }
    }
}
