<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('symbol', 10);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->decimal('rate', 7, 4);
            $table->string('tax_type', 20);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_inclusive')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'tax_type', 'is_default']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('symbol', 20);
            $table->string('unit_type', 20);
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('type', 30);
            $table->boolean('requires_reference')->default(false);
            $table->boolean('is_cash')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active', 'sort_order']);
        });

        Schema::create('vehicle_brands', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vehicle_brand_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->unsignedSmallInteger('start_year')->nullable();
            $table->unsignedSmallInteger('end_year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['vehicle_brand_id', 'is_active']);
        });

        foreach (['vehicle_sizes', 'vehicle_types'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('code', 50);
                $table->string('name');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['company_id', 'code']);
                $table->index(['company_id', 'is_active', 'sort_order']);
            });
        }

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open');
            $table->boolean('is_current')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'start_date', 'end_date']);
            $table->index(['company_id', 'is_current']);
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('prefix', 100);
            $table->unsignedBigInteger('current_number')->default(0);
            $table->unsignedTinyInteger('padding')->default(6);
            $table->string('reset_period', 20)->default('yearly');
            $table->string('period_key', 10)->nullable();
            $table->string('scope_key', 191)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'document_type', 'period_key'], 'document_sequences_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('vehicle_types');
        Schema::dropIfExists('vehicle_sizes');
        Schema::dropIfExists('vehicle_models');
        Schema::dropIfExists('vehicle_brands');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('units');
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('currencies');
    }
};
