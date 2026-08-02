<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['name' => 'customer_payments.approve'],
            ['display_name' => 'customer_payments.approve', 'updated_at' => $now, 'created_at' => $now]
        );

        $permissionId = DB::table('permissions')
            ->where('name', 'customer_payments.approve')
            ->value('id');
        $roleIds = DB::table('roles')
            ->whereIn('name', ['branch_manager', 'company_owner', 'general_manager'])
            ->pluck('id');

        DB::table('role_permissions')->insertOrIgnore(
            $roleIds->map(fn ($roleId) => [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ])->all()
        );
    }

    public function down(): void
    {
        // Intentionally preserved because later explicit grants must not be removed.
    }
};
