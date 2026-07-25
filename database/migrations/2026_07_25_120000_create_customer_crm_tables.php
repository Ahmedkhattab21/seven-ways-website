<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_sources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('assigned_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('customer_code', 50);
            $table->string('customer_type', 30);
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('normalized_phone', 30)->nullable();
            $table->string('alternative_phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->string('commercial_registration', 100)->nullable();
            $table->string('preferred_language', 5)->default('ar');
            $table->decimal('credit_limit', 19, 4)->default(0);
            $table->unsignedSmallInteger('payment_term_days')->default(0);
            $table->string('status', 20)->default('active');
            $table->foreignId('source_id')->nullable()->constrained('customer_sources')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'customer_code']);
            $table->unique(['company_id', 'tax_number']);
            $table->unique(['company_id', 'commercial_registration']);
            $table->index(['company_id', 'assigned_branch_id', 'status']);
            $table->index(['company_id', 'normalized_phone']);
            $table->index(['company_id', 'email']);
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('job_title')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('normalized_phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'is_primary']);
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('address_type', 20);
            $table->char('country_code', 2)->default('SA');
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('street')->nullable();
            $table->string('building_number', 30)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('additional_number', 30)->nullable();
            $table->string('short_address', 30)->nullable();
            $table->text('address_line')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'address_type', 'is_default']);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('vehicle_brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_model_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_type_id')->nullable()->constrained('vehicle_types')->nullOnDelete();
            $table->foreignId('vehicle_size_id')->nullable()->constrained('vehicle_sizes')->nullOnDelete();
            $table->unsignedSmallInteger('manufacturing_year')->nullable();
            $table->string('color', 50)->nullable();
            $table->string('plate_number', 50)->nullable();
            $table->string('normalized_plate_number', 50)->nullable();
            $table->string('vin', 50)->nullable();
            $table->unsignedBigInteger('odometer')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'vin']);
            $table->unique(['company_id', 'normalized_plate_number']);
            $table->index(['company_id', 'customer_id', 'status']);
        });

        Schema::create('vehicle_ownership_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('to_customer_id')->constrained('customers')->restrictOnDelete();
            $table->timestamp('transferred_at');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['vehicle_id', 'transferred_at']);
        });

        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->text('note');
            $table->string('visibility', 20)->default('company');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'visibility', 'branch_id']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('lead_number', 50);
            $table->string('name');
            $table->string('phone', 40);
            $table->string('normalized_phone', 30);
            $table->string('email')->nullable();
            $table->foreignId('vehicle_brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_model_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('vehicle_year')->nullable();
            $table->text('requested_service_text')->nullable();
            $table->foreignId('source_id')->nullable()->constrained('customer_sources')->nullOnDelete();
            $table->string('status', 30)->default('new');
            $table->string('priority', 20)->default('normal');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->text('lost_reason')->nullable();
            $table->foreignId('converted_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'lead_number']);
            $table->index(['company_id', 'branch_id', 'status']);
            $table->index(['company_id', 'normalized_phone']);
            $table->index(['assigned_to', 'next_follow_up_at']);
        });

        Schema::create('lead_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('follow_up_type', 20);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('outcome')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['lead_id', 'scheduled_at']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->morphs('attachable');
            $table->string('category', 50)->nullable();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
            $table->index(['company_id', 'category']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 80);
            $table->nullableMorphs('auditable');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('lead_follow_ups');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('vehicle_ownership_history');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('customer_sources');
    }
};
