<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['name' => 'inventory.count'],
            ['display_name' => 'inventory.count', 'updated_at' => $now, 'created_at' => $now]
        );

        $countPermissionId = DB::table('permissions')->where('name', 'inventory.count')->value('id');
        $postPermissionId = DB::table('permissions')->where('name', 'inventory.post')->value('id');
        $branchManagerRoleIds = DB::table('roles')->where('name', 'branch_manager')->pluck('id');

        DB::table('role_permissions')->insertOrIgnore(
            $branchManagerRoleIds->map(fn ($roleId) => [
                'role_id' => $roleId,
                'permission_id' => $countPermissionId,
            ])->all()
        );

        if ($postPermissionId) {
            DB::table('role_permissions')
                ->whereIn('role_id', $branchManagerRoleIds)
                ->where('permission_id', $postPermissionId)
                ->delete();
        }
    }

    public function down(): void
    {
        // The operational separation is intentionally preserved on rollback.
    }
};
