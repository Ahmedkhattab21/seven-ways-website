<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('quotation_number', 60);
            $table->unsignedInteger('version_number')->default(1);
            $table->foreignId('parent_quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->date('quotation_date');
            $table->date('valid_until');
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->boolean('price_includes_tax')->default(false);
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 19, 4)->default(0);
            $table->decimal('discount_amount', 19, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
            $table->decimal('estimated_material_cost', 19, 4)->nullable();
            $table->decimal('estimated_waste_cost', 19, 4)->nullable();
            $table->decimal('estimated_total_cost', 19, 4)->nullable();
            $table->decimal('estimated_margin', 19, 4)->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('acceptance_method', 20)->nullable();
            $table->string('accepted_by_name')->nullable();
            $table->text('acceptance_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'quotation_number', 'version_number'], 'quotation_version_unique');
            $table->index(['company_id', 'branch_id', 'status', 'valid_until'], 'quotation_scope_status_index');
            $table->index(['customer_id', 'vehicle_id']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 20);
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
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
            $table->decimal('minimum_price_snapshot', 19, 4)->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->decimal('estimated_material_cost', 19, 4)->nullable();
            $table->decimal('estimated_waste_cost', 19, 4)->nullable();
            $table->decimal('estimated_total_cost', 19, 4)->nullable();
            $table->decimal('estimated_margin', 19, 4)->nullable();
            $table->string('price_source', 30);
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['quotation_id', 'sort_order']);
        });

        Schema::create('quotation_item_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('expected_quantity', 16, 6);
            $table->decimal('expected_waste_quantity', 16, 6)->default(0);
            $table->decimal('estimated_unit_cost', 19, 4)->nullable();
            $table->decimal('estimated_material_cost', 19, 4)->nullable();
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('appointment_number', 60);
            $table->foreignId('quotation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->dateTime('scheduled_start');
            $table->dateTime('scheduled_end');
            $table->unsignedInteger('estimated_duration_minutes');
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('booking_source', 20);
            $table->string('priority', 20)->default('normal');
            $table->boolean('deposit_required')->default(false);
            $table->decimal('deposit_amount', 19, 4)->default(0);
            $table->string('deposit_status', 20)->default('not_required');
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('no_show_reason')->nullable();
            $table->text('arrival_notes')->nullable();
            $table->unsignedBigInteger('odometer_snapshot')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'appointment_number']);
            $table->index(['company_id', 'branch_id', 'status', 'scheduled_start'], 'appointment_calendar_index');
            $table->index(['assigned_employee_id', 'scheduled_start', 'scheduled_end'], 'appointment_employee_time_index');
        });

        Schema::create('appointment_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 16, 6);
            $table->unsignedInteger('estimated_duration_minutes');
            $table->decimal('unit_price_snapshot', 19, 4);
            $table->decimal('total_snapshot', 19, 4);
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('planned');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['appointment_id', 'sort_order']);
        });

        Schema::create('appointment_deposits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->string('receipt_number', 60);
            $table->decimal('amount', 19, 4);
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('reference_number')->nullable();
            $table->dateTime('received_at');
            $table->string('status', 20)->default('recorded');
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'receipt_number']);
            $table->index(['appointment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_deposits');
        Schema::dropIfExists('appointment_services');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('quotation_item_materials');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
