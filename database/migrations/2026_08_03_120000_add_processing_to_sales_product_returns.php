<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_product_returns', function (Blueprint $table) {
            $table->timestamp('processed_at')->nullable()->after('stock_movement_id');
            $table->index(['sales_credit_note_id', 'sales_invoice_item_id'], 'sales_product_return_credit_item_index');
        });
    }

    public function down(): void
    {
        Schema::table('sales_product_returns', function (Blueprint $table) {
            $table->dropIndex('sales_product_return_credit_item_index');
            $table->dropColumn('processed_at');
        });
    }
};
