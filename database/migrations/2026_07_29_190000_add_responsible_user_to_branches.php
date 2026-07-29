<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('responsible_user_id')->nullable()->after('company_id')
                ->unique()->constrained('users')->nullOnDelete();
            $table->timestamp('responsible_assigned_at')->nullable()->after('responsible_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['responsible_user_id']);
            $table->dropUnique(['responsible_user_id']);
            $table->dropColumn(['responsible_user_id', 'responsible_assigned_at']);
        });
    }
};
