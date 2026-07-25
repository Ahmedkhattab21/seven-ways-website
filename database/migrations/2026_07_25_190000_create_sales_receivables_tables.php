<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number', 60);
            $table->string('invoice_type', 30);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('warranty_claim_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->boolean('price_includes_tax')->default(false);
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('rounding_amount', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
            $table->decimal('paid_amount', 19, 4)->default(0);
            $table->decimal('credited_amount', 19, 4)->default(0);
            $table->decimal('refunded_amount', 19, 4)->default(0);
            $table->decimal('balance_due', 19, 4)->default(0);
            $table->string('customer_name_snapshot');
            $table->string('customer_tax_number_snapshot')->nullable();
            $table->string('customer_phone_snapshot')->nullable();
            $table->text('customer_address_snapshot')->nullable();
            $table->json('vehicle_snapshot')->nullable();
            $table->text('terms_snapshot')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'invoice_number']);
            $table->index(['company_id', 'branch_id', 'status', 'invoice_date'], 'sales_invoice_scope_status_index');
            $table->index(['customer_id', 'currency_id', 'balance_due'], 'sales_invoice_customer_balance_index');
            $table->index(['work_order_id', 'status']);
        });

        Schema::create('sales_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 30);
            $table->foreignId('work_order_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description');
            $table->decimal('quantity', 16, 6);
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('unit_price', 19, 4);
            $table->decimal('gross_amount', 19, 4);
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('net_amount', 19, 4);
            $table->foreignId('tax_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('total', 19, 4);
            $table->decimal('cost_snapshot', 19, 4)->nullable();
            $table->decimal('margin_snapshot', 19, 4)->nullable();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('issued_movement_id')->nullable();
            $table->decimal('returned_quantity', 16, 6)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['sales_invoice_id', 'sort_order']);
            $table->index(['product_id', 'warehouse_id']);
        });

        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('payment_number', 60);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('recorded');
            $table->date('payment_date');
            $table->decimal('amount', 19, 4);
            $table->decimal('allocated_amount', 19, 4)->default(0);
            $table->decimal('unallocated_amount', 19, 4);
            $table->string('reference_number')->nullable();
            $table->string('source_type', 30);
            $table->foreignId('appointment_deposit_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'payment_number']);
            $table->unique('appointment_deposit_id');
            $table->index(['company_id', 'branch_id', 'status', 'payment_date'], 'payment_scope_status_index');
            $table->index(['customer_id', 'currency_id', 'unallocated_amount'], 'payment_customer_balance_index');
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->timestamp('allocated_at');
            $table->foreignId('allocated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
            $table->index(['customer_payment_id', 'reversed_at']);
            $table->index(['sales_invoice_id', 'reversed_at']);
        });

        Schema::create('sales_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('credit_note_number', 60);
            $table->foreignId('sales_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->date('credit_note_date');
            $table->string('reason_code', 40);
            $table->text('reason');
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
            $table->decimal('applied_amount', 19, 4)->default(0);
            $table->decimal('refunded_amount', 19, 4)->default(0);
            $table->decimal('remaining_amount', 19, 4)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'credit_note_number']);
            $table->index(['company_id', 'branch_id', 'status', 'credit_note_date'], 'credit_note_scope_status_index');
            $table->index(['sales_invoice_id', 'status']);
        });

        Schema::create('sales_credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_invoice_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description');
            $table->decimal('quantity', 16, 6);
            $table->decimal('unit_price', 19, 4);
            $table->decimal('net_amount', 19, 4);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('total', 19, 4);
            $table->timestamps();
        });

        Schema::create('customer_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('refund_number', 60);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sales_credit_note_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->date('refund_date');
            $table->decimal('amount', 19, 4);
            $table->string('reference_number')->nullable();
            $table->text('reason');
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'refund_number']);
            $table->index(['company_id', 'branch_id', 'status', 'refund_date'], 'refund_scope_status_index');
        });

        Schema::table('appointment_deposits', function (Blueprint $table) {
            $table->foreignId('converted_payment_id')->nullable()->after('cancelled_at')
                ->constrained('customer_payments')->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('converted_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_deposits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_payment_id');
            $table->dropColumn('converted_at');
        });
        Schema::dropIfExists('customer_refunds');
        Schema::dropIfExists('sales_credit_note_items');
        Schema::dropIfExists('sales_credit_notes');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('sales_invoice_items');
        Schema::dropIfExists('sales_invoices');
    }
};
