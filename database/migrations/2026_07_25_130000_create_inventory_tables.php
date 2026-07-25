<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('product_brands', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('country_code', 2)->nullable();
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'name']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('product_categories');
            $table->foreignId('brand_id')->nullable()->constrained('product_brands')->nullOnDelete();
            $table->string('sku', 80);
            $table->string('barcode', 120)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('product_type', 40);
            $table->string('tracking_type', 20)->default('quantity');
            $table->foreignId('purchase_unit_id')->constrained('units');
            $table->foreignId('stock_unit_id')->constrained('units');
            $table->foreignId('sale_unit_id')->constrained('units');
            $table->foreignId('default_tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->string('costing_method', 30)->default('weighted_average');
            $table->decimal('standard_cost', 19, 4)->nullable();
            $table->decimal('default_sale_price', 19, 4)->nullable();
            $table->decimal('minimum_stock', 19, 6)->default(0);
            $table->decimal('maximum_stock', 19, 6)->nullable();
            $table->decimal('reorder_quantity', 19, 6)->nullable();
            $table->unsignedSmallInteger('warranty_months')->nullable();
            $table->boolean('is_sellable')->default(true);
            $table->boolean('is_purchasable')->default(true);
            $table->boolean('is_consumable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'sku']);
            $table->unique(['company_id', 'barcode']);
            $table->index(['company_id', 'product_type', 'tracking_type']);
        });

        Schema::create('film_product_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('film_type', 20);
            $table->decimal('default_width', 19, 6)->nullable();
            $table->decimal('default_roll_length', 19, 6)->nullable();
            $table->decimal('low_roll_threshold', 19, 6)->nullable();
            $table->unsignedInteger('thickness_microns')->nullable();
            $table->decimal('visible_light_transmission', 5, 2)->nullable();
            $table->decimal('infrared_rejection', 5, 2)->nullable();
            $table->decimal('uv_rejection', 5, 2)->nullable();
            $table->decimal('heat_rejection', 5, 2)->nullable();
            $table->string('finish')->nullable();
            $table->string('color')->nullable();
            $table->unsignedSmallInteger('manufacturer_warranty_months')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('product_unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_unit_id')->constrained('units');
            $table->foreignId('to_unit_id')->constrained('units');
            $table->decimal('factor', 19, 8);
            $table->boolean('is_purchase_conversion')->default(false);
            $table->boolean('is_sale_conversion')->default(false);
            $table->timestamps();
            $table->unique(['product_id', 'from_unit_id', 'to_unit_id'], 'product_unit_conversion_unique');
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('warehouse_type', 20)->default('other');
            $table->text('address')->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('allows_sale_issue')->default(true);
            $table->boolean('allows_work_order_issue')->default(true);
            $table->boolean('allows_damaged_stock')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'code']);
            $table->index(['company_id', 'branch_id', 'is_active']);
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 19, 6)->default(0);
            $table->decimal('reserved_quantity', 19, 6)->default(0);
            $table->decimal('available_quantity', 19, 6)->default(0);
            $table->decimal('average_cost', 19, 4)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id']);
            $table->index(['company_id', 'branch_id', 'available_quantity']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->string('movement_number')->unique();
            $table->string('movement_type', 40);
            $table->string('direction', 10);
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity', 19, 6);
            $table->foreignId('unit_id')->constrained('units');
            $table->decimal('stock_quantity', 19, 6);
            $table->decimal('unit_cost', 19, 4);
            $table->decimal('total_cost', 19, 4);
            $table->decimal('balance_before', 19, 6);
            $table->decimal('balance_after', 19, 6);
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->foreignId('reversal_of_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->index(['company_id', 'branch_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('inventory_rolls', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->string('roll_number');
            $table->string('supplier_roll_number')->nullable();
            $table->string('batch_number')->nullable();
            $table->decimal('width', 19, 6);
            $table->decimal('original_length', 19, 6);
            $table->decimal('remaining_length', 19, 6);
            $table->decimal('original_area', 19, 6);
            $table->decimal('remaining_area', 19, 6);
            $table->decimal('unit_cost_per_area', 19, 4);
            $table->decimal('total_cost', 19, 4);
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20)->default('available');
            $table->string('location_code')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'roll_number']);
            $table->index(['warehouse_id', 'product_id', 'status']);
        });

        Schema::create('roll_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('inventory_roll_id')->constrained()->cascadeOnDelete();
            $table->string('movement_number')->unique();
            $table->string('movement_type', 30);
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('length_before', 19, 6);
            $table->decimal('length_change', 19, 6);
            $table->decimal('length_after', 19, 6);
            $table->decimal('area_before', 19, 6);
            $table->decimal('area_change', 19, 6);
            $table->decimal('area_after', 19, 6);
            $table->decimal('usable_area', 19, 6)->default(0);
            $table->decimal('waste_area', 19, 6)->default(0);
            $table->decimal('unit_cost_per_area', 19, 4);
            $table->decimal('usable_cost', 19, 4)->default(0);
            $table->decimal('waste_cost', 19, 4)->default(0);
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->foreignId('reversal_of_id')->nullable()->constrained('roll_movements')->nullOnDelete();
            $table->index(['inventory_roll_id', 'occurred_at']);
        });

        Schema::create('roll_scraps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('source_roll_id')->constrained('inventory_rolls');
            $table->string('scrap_code')->unique();
            $table->decimal('width', 19, 6);
            $table->decimal('length', 19, 6);
            $table->decimal('area', 19, 6);
            $table->decimal('unit_cost_per_area', 19, 4);
            $table->decimal('total_cost', 19, 4);
            $table->string('status', 20)->default('available');
            $table->string('location_code')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['warehouse_id', 'status', 'area']);
        });

        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('inventory_roll_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('roll_scrap_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_type', 60);
            $table->unsignedBigInteger('reference_id');
            $table->decimal('quantity', 19, 6)->default(0);
            $table->decimal('length', 19, 6)->default(0);
            $table->decimal('area', 19, 6)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
            $table->index(['warehouse_id', 'product_id', 'status']);
        });

        $this->createOpeningTables();
        $this->createAdjustmentTables();
        $this->createCountTables();
    }

    private function createOpeningTables(): void
    {
        Schema::create('stock_opening_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('document_number')->unique();
            $table->string('status', 20)->default('draft');
            $table->date('opening_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
        Schema::create('stock_opening_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opening_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 19, 6)->default(0);
            $table->decimal('unit_cost', 19, 4)->default(0);
            $table->string('roll_number')->nullable();
            $table->decimal('roll_width', 19, 6)->nullable();
            $table->decimal('roll_length', 19, 6)->nullable();
            $table->foreignId('inventory_roll_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    private function createAdjustmentTables(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('document_number')->unique();
            $table->string('adjustment_type', 20);
            $table->string('status', 30)->default('draft');
            $table->date('adjustment_date');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('inventory_roll_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('roll_scrap_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 19, 6)->default(0);
            $table->decimal('unit_cost', 19, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    private function createCountTables(): void
    {
        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('document_number')->unique();
            $table->string('status', 20)->default('draft');
            $table->date('count_date');
            $table->string('scope_type', 20)->default('full');
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->timestamp('snapshot_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('inventory_roll_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('system_quantity', 19, 6)->default(0);
            $table->decimal('counted_quantity', 19, 6)->nullable();
            $table->decimal('difference_quantity', 19, 6)->nullable();
            $table->decimal('system_length', 19, 6)->nullable();
            $table->decimal('counted_length', 19, 6)->nullable();
            $table->decimal('difference_length', 19, 6)->nullable();
            $table->decimal('system_area', 19, 6)->nullable();
            $table->decimal('counted_area', 19, 6)->nullable();
            $table->decimal('difference_area', 19, 6)->nullable();
            $table->decimal('unit_cost', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['inventory_count_id', 'product_id', 'inventory_roll_id'], 'inventory_count_line_unique');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ([
            'inventory_count_items', 'inventory_counts', 'stock_adjustment_items', 'stock_adjustments',
            'stock_opening_items', 'stock_opening_documents', 'inventory_reservations', 'roll_scraps',
            'roll_movements', 'inventory_rolls', 'stock_movements', 'stock_balances', 'warehouses',
            'product_unit_conversions', 'film_product_profiles', 'products', 'product_brands',
            'product_categories',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
    }
};
