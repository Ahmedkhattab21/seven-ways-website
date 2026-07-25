<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('work_order_number', 60);
            $table->foreignId('appointment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->string('priority', 20)->default('normal');
            foreach (['check_in_at', 'expected_start_at', 'started_at', 'expected_finish_at', 'finished_at', 'ready_for_quality_at', 'delivered_at'] as $column) {
                $table->timestamp($column)->nullable();
            }
            $table->unsignedBigInteger('odometer_at_check_in')->nullable();
            $table->decimal('fuel_level', 5, 2)->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            foreach (['estimated_subtotal', 'estimated_tax', 'estimated_total'] as $column) {
                $table->decimal($column, 19, 4)->default(0);
            }
            $table->decimal('estimated_material_cost', 19, 4)->nullable();
            foreach (['actual_material_cost', 'actual_waste_cost', 'actual_labor_cost', 'actual_total_cost'] as $column) {
                $table->decimal($column, 19, 4)->default(0);
            }
            $table->decimal('estimated_margin', 19, 4)->nullable();
            $table->decimal('actual_margin', 19, 4)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'work_order_number']);
            $table->index(['company_id', 'branch_id', 'status']);
            $table->index(['appointment_id', 'status']);
            $table->index(['vehicle_id', 'created_at']);
        });

        Schema::create('work_order_services', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 16, 6)->default(1);
            $table->string('status', 30)->default('planned');
            $table->unsignedInteger('planned_duration_minutes')->default(0);
            $table->unsignedInteger('actual_duration_minutes')->nullable();
            $table->decimal('unit_price_snapshot', 19, 4)->default(0);
            $table->decimal('total_snapshot', 19, 4)->default(0);
            $table->decimal('estimated_material_cost', 19, 4)->nullable();
            foreach (['actual_material_cost', 'actual_waste_cost', 'actual_labor_cost', 'actual_total_cost'] as $column) {
                $table->decimal($column, 19, 4)->default(0);
            }
            foreach (['started_at', 'paused_at', 'completed_at', 'cancelled_at'] as $column) {
                $table->timestamp($column)->nullable();
            }
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['work_order_id', 'status']);
        });

        Schema::create('work_order_service_technicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('role', 20)->default('technician');
            $table->boolean('is_primary')->default(false);
            foreach (['assigned_at', 'started_at', 'finished_at'] as $column) {
                $table->timestamp($column)->nullable();
            }
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->decimal('hourly_cost_snapshot', 19, 4)->nullable();
            $table->decimal('labor_cost', 19, 4)->default(0);
            $table->foreignId('commission_rule_id')->nullable()->constrained('service_commission_rules')->nullOnDelete();
            $table->decimal('estimated_commission', 19, 4)->nullable();
            $table->string('status', 20)->default('assigned');
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['work_order_service_id', 'employee_id'], 'work_service_technician_unique');
        });

        Schema::create('work_order_service_time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('action', 20);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['work_order_service_id', 'employee_id', 'ended_at'], 'work_log_open_index');
        });

        Schema::create('vehicle_inspections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('inspection_type', 20)->default('check_in');
            $table->string('status', 30)->default('draft');
            $table->unsignedBigInteger('odometer')->nullable();
            $table->decimal('fuel_level', 5, 2)->nullable();
            $table->string('customer_signature_path')->nullable();
            $table->timestamp('customer_approved_at')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approved_by_customer_name')->nullable();
            $table->text('general_notes')->nullable();
            $table->timestamps();
            $table->unique(['work_order_id', 'inspection_type']);
        });

        Schema::create('vehicle_inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('vehicle_inspections')->restrictOnDelete();
            $table->string('section', 60);
            $table->string('item_code', 80);
            $table->string('item_name');
            $table->string('condition', 30);
            $table->string('severity', 20)->nullable();
            $table->boolean('is_existing_damage')->default(false);
            $table->text('notes')->nullable();
            $table->decimal('x_position', 5, 2)->nullable();
            $table->decimal('y_position', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['inspection_id', 'item_code']);
        });

        Schema::create('work_order_materials', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('roll_id')->nullable()->constrained('inventory_rolls')->nullOnDelete();
            $table->foreignId('scrap_id')->nullable()->constrained('roll_scraps')->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('inventory_reservations')->nullOnDelete();
            $table->string('material_type', 20);
            foreach (['expected_quantity', 'issued_quantity', 'used_quantity', 'returned_quantity', 'waste_quantity'] as $column) {
                $table->decimal($column, 19, 6)->default(0);
            }
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            foreach (['unit_cost', 'issued_cost', 'used_cost', 'waste_cost'] as $column) {
                $table->decimal($column, 19, 4)->default(0);
            }
            $table->string('status', 30)->default('planned');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['work_order_id', 'status']);
        });

        Schema::create('work_order_waste_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('roll_id')->nullable()->constrained('inventory_rolls')->nullOnDelete();
            $table->foreignId('scrap_id')->nullable()->constrained('roll_scraps')->nullOnDelete();
            $table->decimal('quantity', 19, 6)->nullable();
            $table->decimal('area', 19, 6)->nullable();
            $table->decimal('unit_cost', 19, 4)->default(0);
            $table->decimal('total_cost', 19, 4)->default(0);
            $table->string('reason_code', 40);
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('requires_approval')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('work_order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['work_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['work_order_status_logs', 'work_order_waste_records', 'work_order_materials', 'vehicle_inspection_items', 'vehicle_inspections', 'work_order_service_time_logs', 'work_order_service_technicians', 'work_order_services', 'work_orders'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
