<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('service_type', 40)->nullable();
            $table->string('scope_key', 80);
            $table->string('code', 60);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'scope_key', 'version'], 'quality_template_scope_version_unique');
            $table->index(['company_id', 'scope_key', 'is_default', 'is_active'], 'quality_template_resolution_index');
        });

        Schema::create('quality_checklist_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_checklist_template_id');
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 60);
            $table->string('check_type', 30);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_critical')->default(false);
            $table->boolean('requires_photo_on_failure')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('quality_checklist_template_id', 'quality_template_item_template_fk')
                ->references('id')->on('quality_checklist_templates')->restrictOnDelete();
            $table->unique(['quality_checklist_template_id', 'code'], 'quality_template_item_code_unique');
        });

        Schema::create('quality_checks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->string('quality_check_number', 60);
            $table->unsignedInteger('round_number');
            $table->string('status', 30)->default('draft');
            $table->foreignId('checklist_template_id')->nullable()->constrained('quality_checklist_templates')->restrictOnDelete();
            $table->unsignedInteger('template_version')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('checked_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('overall_result', 20)->nullable();
            $table->boolean('requires_rework')->default(false);
            $table->text('general_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'quality_check_number']);
            $table->unique(['work_order_id', 'round_number']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('quality_check_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_check_id')->constrained()->restrictOnDelete();
            $table->foreignId('template_item_id')->nullable()->constrained('quality_checklist_template_items')->restrictOnDelete();
            $table->foreignId('work_order_service_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->string('category', 60);
            $table->string('check_type', 30);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_critical')->default(false);
            $table->string('result', 30)->default('pending');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->decimal('measurement_value', 19, 6)->nullable();
            $table->string('measurement_unit', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('photo_required')->default(false);
            $table->text('not_applicable_reason')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['quality_check_id', 'code']);
        });

        Schema::create('rework_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('quality_check_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('warranty_claim_id')->nullable();
            $table->string('rework_number', 60);
            $table->string('status', 30)->default('draft');
            $table->string('reason_code', 40)->default('other');
            $table->text('reason');
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('defective_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('defective_roll_id')->nullable()->constrained('inventory_rolls')->nullOnDelete();
            $table->string('defective_batch_number')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('additional_material_cost', 19, 4)->default(0);
            $table->decimal('additional_waste_cost', 19, 4)->default(0);
            $table->decimal('additional_labor_cost', 19, 4)->default(0);
            $table->decimal('total_rework_cost', 19, 4)->default(0);
            $table->boolean('is_customer_chargeable')->default(false);
            $table->decimal('customer_charge_amount', 19, 4)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'rework_number']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('rework_order_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rework_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_service_id')->constrained()->restrictOnDelete();
            $table->text('reason');
            $table->text('required_action');
            $table->string('status', 30)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['rework_order_id', 'work_order_service_id'], 'rework_service_unique');
        });

        Schema::table('work_order_materials', function (Blueprint $table) {
            $table->foreignId('rework_order_id')->nullable()->after('work_order_service_id')
                ->constrained('rework_orders')->restrictOnDelete();
            $table->index(['rework_order_id', 'status']);
        });

        Schema::table('vehicle_inspections', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('customer_approved_at');
            $table->json('delivered_items')->nullable()->after('completed_at');
            $table->text('customer_notes')->nullable()->after('delivered_items');
            $table->string('receiver_name')->nullable()->after('customer_notes');
            $table->string('receiver_contact')->nullable()->after('receiver_name');
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->string('delivery_receiver_name')->nullable()->after('delivered_at');
            $table->string('delivery_receiver_contact')->nullable()->after('delivery_receiver_name');
            $table->string('delivery_signature_path')->nullable()->after('delivery_receiver_contact');
        });

        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('warranty_number', 60);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->date('start_date');
            $table->date('end_date');
            $table->json('terms_snapshot');
            $table->string('qr_token', 96)->unique();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'warranty_number']);
            $table->index(['company_id', 'branch_id', 'status']);
            $table->index(['work_order_id', 'status']);
        });

        Schema::create('warranty_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('roll_id')->nullable()->constrained('inventory_rolls')->nullOnDelete();
            $table->string('batch_number')->nullable();
            $table->string('vehicle_section')->nullable();
            $table->foreignId('technician_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedSmallInteger('warranty_months');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('coverage_terms')->nullable();
            $table->text('exclusions')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['warranty_id', 'work_order_service_id'], 'warranty_service_unique');
        });

        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('claim_number', 60);
            $table->foreignId('warranty_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('submitted');
            $table->text('complaint');
            $table->timestamp('reported_at');
            $table->timestamp('inspection_scheduled_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('decision_at')->nullable();
            $table->string('decision', 30)->default('pending');
            $table->boolean('is_free')->default(false);
            $table->decimal('customer_charge_amount', 19, 4)->default(0);
            $table->decimal('estimated_company_cost', 19, 4)->default(0);
            $table->decimal('actual_company_cost', 19, 4)->default(0);
            $table->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'claim_number']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::table('rework_orders', function (Blueprint $table) {
            $table->foreign('warranty_claim_id')->references('id')->on('warranty_claims')->restrictOnDelete();
            $table->index('warranty_claim_id');
        });

        Schema::create('warranty_claim_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_claim_id')->constrained()->restrictOnDelete();
            $table->foreignId('warranty_item_id')->constrained()->restrictOnDelete();
            $table->string('issue_type', 40);
            $table->text('description');
            $table->string('inspection_result', 40)->nullable();
            $table->string('decision', 30)->default('pending');
            $table->decimal('coverage_percentage', 5, 2)->default(0);
            $table->decimal('estimated_cost', 19, 4)->default(0);
            $table->decimal('actual_cost', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['warranty_claim_id', 'warranty_item_id'], 'warranty_claim_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_claim_items');
        Schema::table('rework_orders', function (Blueprint $table) {
            $table->dropForeign(['warranty_claim_id']);
            $table->dropIndex(['warranty_claim_id']);
        });
        Schema::dropIfExists('warranty_claims');
        Schema::dropIfExists('warranty_items');
        Schema::dropIfExists('warranties');
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_receiver_name', 'delivery_receiver_contact', 'delivery_signature_path']);
        });
        Schema::table('vehicle_inspections', function (Blueprint $table) {
            $table->dropColumn(['completed_at', 'delivered_items', 'customer_notes', 'receiver_name', 'receiver_contact']);
        });
        Schema::table('work_order_materials', function (Blueprint $table) {
            $table->dropForeign(['rework_order_id']);
            $table->dropIndex(['rework_order_id', 'status']);
            $table->dropColumn('rework_order_id');
        });
        Schema::dropIfExists('rework_order_services');
        Schema::dropIfExists('rework_orders');
        Schema::dropIfExists('quality_check_items');
        Schema::dropIfExists('quality_checks');
        Schema::dropIfExists('quality_checklist_template_items');
        Schema::dropIfExists('quality_checklist_templates');
    }
};
