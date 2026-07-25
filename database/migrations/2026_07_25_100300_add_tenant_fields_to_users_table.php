<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('status', 20)->default('active')->after('password')->index();
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->ipAddress('last_login_ip')->nullable()->after('last_login_at');
            $table->index(['company_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id', 'branch_id']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'company_id', 'branch_id', 'phone', 'status', 'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
