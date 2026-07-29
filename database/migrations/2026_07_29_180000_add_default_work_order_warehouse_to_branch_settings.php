<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_settings', function (Blueprint $table) {
            $table->foreignId('default_work_order_warehouse_id')
                ->nullable()
                ->after('default_payment_method_id')
                ->constrained('warehouses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('branch_settings', function (Blueprint $table) {
            $table->dropForeign(['default_work_order_warehouse_id']);
            $table->dropColumn('default_work_order_warehouse_id');
        });
    }
};
