<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_settings', function (Blueprint $table) {
            $table->foreignId('default_tax_id')->nullable()->after('branch_id')->constrained('taxes')->nullOnDelete();
            $table->foreignId('default_payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('appointment_prefix', 20)->nullable()->after('quotation_prefix');
            $table->string('purchase_order_prefix', 20)->nullable()->after('work_order_prefix');
            $table->string('stock_transfer_prefix', 20)->nullable()->after('purchase_order_prefix');
            $table->time('working_day_start')->nullable();
            $table->time('working_day_end')->nullable();
            $table->json('weekend_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('branch_settings', function (Blueprint $table) {
            $table->dropForeign(['default_tax_id']);
            $table->dropForeign(['default_payment_method_id']);
            $table->dropColumn([
                'default_tax_id', 'default_payment_method_id', 'appointment_prefix',
                'purchase_order_prefix', 'stock_transfer_prefix', 'working_day_start',
                'working_day_end', 'weekend_days',
            ]);
        });
    }
};
