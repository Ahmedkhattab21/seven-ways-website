<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->foreignId('cash_box_id')->nullable()->after('payment_method_id')
                ->constrained()->restrictOnDelete();
            $table->foreignId('cash_box_session_id')->nullable()->after('cash_box_id')
                ->constrained()->restrictOnDelete();
            $table->foreignId('intended_sales_invoice_id')->nullable()->after('cash_box_session_id')
                ->constrained('sales_invoices')->restrictOnDelete();
            $table->decimal('intended_allocation_amount', 19, 4)->nullable()
                ->after('intended_sales_invoice_id');
            $table->index(
                ['company_id', 'cash_box_id', 'cash_box_session_id'],
                'customer_payment_cash_context'
            );
        });

        Schema::table('cash_receipts', function (Blueprint $table) {
            $table->foreignId('customer_payment_id')->nullable()->after('customer_id')
                ->constrained()->restrictOnDelete();
            $table->unique('customer_payment_id', 'cash_receipt_customer_payment_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cash_receipts', function (Blueprint $table) {
            $table->dropUnique('cash_receipt_customer_payment_unique');
            $table->dropConstrainedForeignId('customer_payment_id');
        });

        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropIndex('customer_payment_cash_context');
            $table->dropConstrainedForeignId('intended_sales_invoice_id');
            $table->dropConstrainedForeignId('cash_box_session_id');
            $table->dropConstrainedForeignId('cash_box_id');
            $table->dropColumn('intended_allocation_amount');
        });
    }
};
