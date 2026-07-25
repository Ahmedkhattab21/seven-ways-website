<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A pre-ERP marketing table used this generic name. Preserve it verbatim
        // and reserve `services` for the tenant-aware ERP catalog.
        if (Schema::hasTable('services') && ! Schema::hasColumn('services', 'company_id')) {
            if (Schema::hasTable('legacy_website_services')) {
                throw new RuntimeException('Both legacy services tables exist; manual reconciliation is required.');
            }
            Schema::rename('services', 'legacy_website_services');
        }

        if (! Schema::hasTable('service_categories')) {
            Schema::create('service_categories', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('service_categories')->restrictOnDelete();
                $table->string('code', 50);
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('icon', 40)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['company_id', 'code']);
                $table->index(['company_id', 'is_active', 'sort_order']);
            });
        }

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_category_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->string('service_type', 40)->index();
            $table->string('pricing_type', 30)->index();
            $table->unsignedInteger('default_duration_minutes');
            $table->foreignId('default_tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->foreignId('pricing_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->unsignedSmallInteger('default_warranty_months')->nullable();
            $table->boolean('requires_vehicle')->default(true);
            $table->boolean('requires_inspection')->default(false);
            $table->boolean('requires_quality_check')->default(false);
            $table->boolean('allows_multiple_technicians')->default(false);
            $table->boolean('is_package_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'service_category_id', 'is_active'], 'services_catalog_index');
        });

        Schema::create('branch_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->boolean('is_available')->default(true);
            $table->boolean('booking_enabled')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->unsignedInteger('minimum_notice_minutes')->nullable();
            $table->unsignedSmallInteger('maximum_daily_capacity')->nullable();
            $table->unsignedInteger('default_duration_minutes')->nullable();
            $table->decimal('default_price', 14, 4)->nullable();
            $table->decimal('minimum_price', 14, 4)->nullable();
            $table->decimal('maximum_discount_percentage', 7, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'service_id']);
            $table->index(['company_id', 'branch_id', 'is_available']);
        });

        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_size_id')->nullable()->constrained('vehicle_sizes')->restrictOnDelete();
            $table->foreignId('vehicle_type_id')->nullable()->constrained('vehicle_types')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->decimal('price', 14, 4);
            $table->decimal('minimum_price', 14, 4)->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'branch_id', 'service_id', 'effective_from'], 'service_prices_resolution_index');
            $table->index(['service_id', 'vehicle_size_id', 'vehicle_type_id', 'unit_id'], 'service_prices_scope_index');
        });

        Schema::create('service_material_requirements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_size_id')->nullable()->constrained('vehicle_sizes')->restrictOnDelete();
            $table->foreignId('vehicle_type_id')->nullable()->constrained('vehicle_types')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('requirement_type', 40);
            $table->decimal('expected_quantity', 16, 6);
            $table->decimal('expected_waste_percentage', 7, 4)->default(0);
            $table->decimal('minimum_quantity', 16, 6)->nullable();
            $table->decimal('maximum_quantity', 16, 6)->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('allow_substitution')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(
                ['service_id', 'vehicle_size_id', 'vehicle_type_id', 'product_id', 'unit_id'],
                'service_material_scope_unique'
            );
        });

        Schema::create('service_roll_consumption_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_size_id')->nullable()->constrained('vehicle_sizes')->restrictOnDelete();
            $table->foreignId('vehicle_type_id')->nullable()->constrained('vehicle_types')->restrictOnDelete();
            $table->foreignId('film_product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('coverage_type', 40);
            $table->decimal('expected_width', 12, 4)->nullable();
            $table->decimal('expected_length', 12, 4)->nullable();
            $table->decimal('expected_area', 16, 6);
            $table->decimal('expected_waste_percentage', 7, 4)->default(0);
            $table->decimal('minimum_scrap_width', 12, 4)->nullable();
            $table->decimal('minimum_scrap_length', 12, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(
                ['service_id', 'vehicle_size_id', 'vehicle_type_id', 'film_product_id', 'coverage_type'],
                'service_roll_profile_scope_unique'
            );
        });

        Schema::create('service_material_substitutes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_material_requirement_id');
            $table->foreignId('substitute_product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->decimal('conversion_factor', 16, 6)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('service_material_requirement_id', 'svc_mat_sub_requirement_fk')
                ->references('id')->on('service_material_requirements')->cascadeOnDelete();
            $table->unique(['service_material_requirement_id', 'substitute_product_id'], 'service_material_substitute_unique');
        });

        Schema::create('employee_service_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('skill_level', 20);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('certified_at')->nullable();
            $table->date('certification_expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'service_id']);
            $table->index(['company_id', 'branch_id', 'is_active']);
        });

        Schema::create('service_commission_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('commission_type', 20);
            $table->decimal('commission_value', 14, 4);
            $table->string('calculation_base', 30);
            $table->decimal('minimum_amount', 14, 4)->nullable();
            $table->decimal('maximum_amount', 14, 4)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'service_id', 'effective_from'], 'service_commission_resolution_index');
        });

        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('package_type', 20);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active', 'start_date']);
        });

        Schema::create('service_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 4)->default(1);
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['service_package_id', 'service_id']);
        });

        Schema::create('branch_service_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_package_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_size_id')->nullable()->constrained('vehicle_sizes')->restrictOnDelete();
            $table->decimal('price', 14, 4);
            $table->decimal('minimum_price', 14, 4)->nullable();
            $table->boolean('is_available')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'service_package_id', 'effective_from'], 'branch_package_resolution_index');
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('promotion_type', 20);
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 14, 4);
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active', 'start_at', 'end_at']);
        });

        Schema::create('promotion_services', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['promotion_id', 'service_id']);
        });
        Schema::create('promotion_packages', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_package_id')->constrained()->cascadeOnDelete();
            $table->primary(['promotion_id', 'service_package_id']);
        });
        Schema::create('promotion_branches', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->primary(['promotion_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_branches');
        Schema::dropIfExists('promotion_packages');
        Schema::dropIfExists('promotion_services');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('branch_service_packages');
        Schema::dropIfExists('service_package_items');
        Schema::dropIfExists('service_packages');
        Schema::dropIfExists('service_commission_rules');
        Schema::dropIfExists('employee_service_skills');
        Schema::dropIfExists('service_material_substitutes');
        Schema::dropIfExists('service_roll_consumption_profiles');
        Schema::dropIfExists('service_material_requirements');
        Schema::dropIfExists('service_prices');
        Schema::dropIfExists('branch_services');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
        if (Schema::hasTable('legacy_website_services') && ! Schema::hasTable('services')) {
            Schema::rename('legacy_website_services', 'services');
        }
    }
};
