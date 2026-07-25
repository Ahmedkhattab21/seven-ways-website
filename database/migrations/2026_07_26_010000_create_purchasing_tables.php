<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('supplier_code', 60);
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('supplier_type', 30)->default('other');
            $table->string('tax_number', 60)->nullable();
            $table->string('commercial_registration', 60)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website')->nullable();
            $table->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('payment_terms_days')->default(0);
            $table->decimal('credit_limit', 19, 4)->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'supplier_code']);
            $table->unique(['company_id', 'tax_number']);
            $table->index(['company_id', 'status', 'name']);
        });

        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('job_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['supplier_id', 'is_primary']);
        });

        Schema::create('supplier_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('address_type', 20);
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('address_line');
            $table->string('postal_code', 30)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['supplier_id', 'address_type', 'is_primary'], 'supplier_address_primary_index');
        });

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->string('supplier_product_name')->nullable();
            $table->foreignId('purchase_unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('conversion_factor', 16, 6)->default(1);
            $table->decimal('last_purchase_price', 19, 4)->nullable();
            $table->decimal('default_purchase_price', 19, 4)->nullable();
            $table->decimal('minimum_order_quantity', 16, 6)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'product_id']);
            $table->index(['product_id', 'is_preferred', 'is_active']);
        });

        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('requisition_number', 60);
            $table->string('status', 30)->default('draft');
            $table->date('request_date');
            $table->date('required_date')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('department')->nullable();
            $table->text('purpose');
            $table->text('notes')->nullable();
            $table->decimal('estimated_total', 19, 4)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'branch_id', 'requisition_number'], 'purchase_requisition_number_unique');
            $table->index(['company_id', 'branch_id', 'status', 'request_date'], 'purchase_requisition_scope_index');
        });

        Schema::create('purchase_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('requested_quantity', 16, 6);
            $table->decimal('approved_quantity', 16, 6)->nullable();
            $table->decimal('estimated_unit_cost', 19, 4)->nullable();
            $table->decimal('estimated_total', 19, 4)->nullable();
            $table->foreignId('preferred_supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->date('required_date')->nullable();
            $table->text('purpose')->nullable();
            $table->string('status', 30)->default('pending');
            $table->decimal('ordered_quantity', 16, 6)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['purchase_requisition_id', 'status']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('purchase_order_number', 60);
            $table->string('status', 30)->default('draft');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate', 19, 8)->default(1);
            $table->unsignedInteger('payment_terms_days')->default(0);
            $table->text('shipping_address_snapshot')->nullable();
            $table->string('supplier_name_snapshot');
            $table->string('supplier_tax_number_snapshot', 60)->nullable();
            $table->text('supplier_address_snapshot')->nullable();
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('shipping_amount', 19, 4)->default(0);
            $table->decimal('other_charges', 19, 4)->default(0);
            $table->decimal('rounding_amount', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
            $table->decimal('received_amount', 19, 4)->default(0);
            $table->decimal('invoiced_amount', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms_snapshot')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'branch_id', 'purchase_order_number'], 'purchase_order_number_unique');
            $table->index(['company_id', 'branch_id', 'supplier_id', 'status'], 'purchase_order_scope_index');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_requisition_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->foreignId('purchase_unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('stock_unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('conversion_factor', 16, 6)->default(1);
            $table->decimal('ordered_quantity', 16, 6);
            $table->decimal('received_quantity', 16, 6)->default(0);
            $table->decimal('returned_quantity', 16, 6)->default(0);
            $table->decimal('invoiced_quantity', 16, 6)->default(0);
            $table->decimal('unit_price', 19, 4);
            $table->decimal('gross_amount', 19, 4);
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('net_amount', 19, 4);
            $table->foreignId('tax_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('total', 19, 4);
            $table->unsignedInteger('expected_roll_count')->nullable();
            $table->decimal('expected_roll_width', 16, 6)->nullable();
            $table->decimal('expected_roll_length', 16, 6)->nullable();
            $table->boolean('batch_required')->default(false);
            $table->boolean('expiry_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['purchase_order_id', 'sort_order']);
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('goods_receipt_number', 60);
            $table->string('status', 30)->default('draft');
            $table->date('receipt_date');
            $table->string('supplier_delivery_note')->nullable();
            $table->string('supplier_invoice_reference')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'branch_id', 'goods_receipt_number'], 'goods_receipt_number_unique');
            $table->index(['company_id', 'branch_id', 'status', 'receipt_date'], 'goods_receipt_scope_index');
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('conversion_factor', 16, 6)->default(1);
            $table->decimal('ordered_quantity_snapshot', 16, 6)->nullable();
            $table->decimal('received_quantity', 16, 6);
            $table->decimal('accepted_quantity', 16, 6);
            $table->decimal('rejected_quantity', 16, 6)->default(0);
            $table->decimal('free_quantity', 16, 6)->default(0);
            $table->decimal('unit_cost', 19, 4);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('total_cost', 19, 4);
            $table->string('batch_number')->nullable();
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('serial_number')->nullable();
            $table->unsignedInteger('roll_count')->nullable();
            $table->decimal('roll_width', 16, 6)->nullable();
            $table->decimal('roll_length', 16, 6)->nullable();
            $table->decimal('roll_core_diameter', 16, 6)->nullable();
            $table->decimal('roll_film_thickness', 16, 6)->nullable();
            $table->json('rolls')->nullable();
            $table->string('condition', 30)->default('good');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('stock_movement_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->index(['goods_receipt_id', 'purchase_order_item_id'], 'goods_receipt_item_order_index');
        });

        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 100);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('received_quantity', 16, 6);
            $table->decimal('available_quantity', 16, 6);
            $table->decimal('unit_cost', 19, 4);
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['product_id', 'warehouse_id', 'batch_number'], 'inventory_batch_scope_unique');
            $table->index(['company_id', 'warehouse_id', 'status']);
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('purchase_return_number', 60);
            $table->string('status', 30)->default('draft');
            $table->date('return_date');
            $table->text('reason');
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'branch_id', 'purchase_return_number'], 'purchase_return_number_unique');
            $table->index(['company_id', 'branch_id', 'status', 'return_date'], 'purchase_return_scope_index');
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('roll_id')->nullable()->constrained('inventory_rolls')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('inventory_batches')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 16, 6);
            $table->decimal('unit_cost', 19, 4);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('total', 19, 4);
            $table->foreignId('stock_movement_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reason_code', 30);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('supplier_invoice_number', 100);
            $table->string('internal_invoice_number', 60);
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('shipping_amount', 19, 4)->default(0);
            $table->decimal('other_charges', 19, 4)->default(0);
            $table->decimal('rounding_amount', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
            $table->decimal('paid_amount', 19, 4)->default(0);
            $table->decimal('credited_amount', 19, 4)->default(0);
            $table->decimal('balance_due', 19, 4)->default(0);
            $table->string('supplier_name_snapshot');
            $table->string('supplier_tax_number_snapshot', 60)->nullable();
            $table->text('supplier_address_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'supplier_id', 'supplier_invoice_number'], 'supplier_invoice_external_unique');
            $table->unique(['company_id', 'branch_id', 'internal_invoice_number'], 'supplier_invoice_internal_unique');
            $table->index(['company_id', 'branch_id', 'status', 'due_date'], 'supplier_invoice_scope_index');
        });

        Schema::create('supplier_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description');
            $table->decimal('quantity', 16, 6);
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->decimal('unit_price', 19, 4);
            $table->decimal('net_amount', 19, 4);
            $table->foreignId('tax_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('total', 19, 4);
            $table->decimal('matched_quantity', 16, 6)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['supplier_invoice_id', 'sort_order']);
        });

        Schema::create('supplier_invoice_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('matched_quantity', 16, 6);
            $table->decimal('po_unit_price', 19, 4)->nullable();
            $table->decimal('invoice_unit_price', 19, 4);
            $table->decimal('price_variance', 19, 4)->default(0);
            $table->decimal('quantity_variance', 16, 6)->default(0);
            $table->string('status', 30);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('approval_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('purchase_return_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('credit_note_number', 60);
            $table->string('supplier_credit_number')->nullable();
            $table->string('status', 30)->default('draft');
            $table->date('credit_date');
            $table->text('reason');
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
            $table->decimal('applied_amount', 19, 4)->default(0);
            $table->decimal('remaining_amount', 19, 4)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'branch_id', 'credit_note_number'], 'supplier_credit_note_number_unique');
            $table->index(['supplier_invoice_id', 'status']);
        });

        Schema::create('supplier_credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_invoice_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description');
            $table->decimal('quantity', 16, 6);
            $table->decimal('unit_price', 19, 4);
            $table->decimal('net_amount', 19, 4);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('total', 19, 4);
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('payment_number', 60);
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->date('payment_date');
            $table->decimal('amount', 19, 4);
            $table->decimal('allocated_amount', 19, 4)->default(0);
            $table->decimal('unallocated_amount', 19, 4);
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'branch_id', 'payment_number'], 'supplier_payment_number_unique');
            $table->index(['company_id', 'branch_id', 'supplier_id', 'status'], 'supplier_payment_scope_index');
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->timestamp('allocated_at');
            $table->foreignId('allocated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
            $table->index(['supplier_payment_id', 'reversed_at'], 'supplier_payment_allocation_active_index');
            $table->index(['supplier_invoice_id', 'reversed_at'], 'supplier_invoice_allocation_active_index');
        });

        Schema::table('inventory_rolls', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->after('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->after('purchase_order_item_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_rolls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('goods_receipt_item_id');
            $table->dropConstrainedForeignId('purchase_order_item_id');
            $table->dropConstrainedForeignId('supplier_id');
        });
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_credit_note_items');
        Schema::dropIfExists('supplier_credit_notes');
        Schema::dropIfExists('supplier_invoice_matches');
        Schema::dropIfExists('supplier_invoice_items');
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_requisition_items');
        Schema::dropIfExists('purchase_requisitions');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('supplier_addresses');
        Schema::dropIfExists('supplier_contacts');
        Schema::dropIfExists('suppliers');
    }
};
