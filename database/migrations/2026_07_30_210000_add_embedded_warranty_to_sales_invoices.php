<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'services', 'service_packages'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'requires_warranty')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->boolean('requires_warranty')->default(false);
                    $table->string('default_warranty_film_type')->nullable();
                    $table->unsignedInteger('default_warranty_duration_value')->nullable();
                    $table->string('default_warranty_duration_unit', 20)->nullable();
                    $table->string('default_warranty_application_area')->nullable();
                    $table->text('default_warranty_terms')->nullable();
                    $table->text('default_warranty_notes')->nullable();
                });
            }
        }

        if (! Schema::hasColumn('sales_invoice_items', 'warranty_applies')) {
            Schema::table('sales_invoice_items', function (Blueprint $table) {
                $table->boolean('warranty_applies')->default(false)->after('returned_quantity');
                $table->json('warranty_snapshot')->nullable()->after('warranty_applies');
                $table->index(['sales_invoice_id', 'warranty_applies'], 'invoice_item_warranty_index');
            });
        }

        if (! Schema::hasColumn('companies', 'invoice_print_settings')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->json('invoice_print_settings')->nullable();
            });
        }

        if (! Schema::hasTable('invoice_shares')) {
            Schema::create('invoice_shares', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
                $table->string('channel', 20)->default('whatsapp');
                $table->string('destination', 30);
                $table->string('status', 20)->default('generated');
                $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
                $table->dateTime('generated_at');
                $table->dateTime('opened_at')->nullable();
                $table->dateTime('failed_at')->nullable();
                $table->dateTime('sent_at')->nullable();
                $table->dateTime('expires_at');
                $table->text('failure_reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'branch_id', 'status'], 'invoice_share_scope_status_index');
                $table->index(['sales_invoice_id', 'created_at']);
            });
        }

        DB::table('products')->where('warranty_months', '>', 0)->update([
            'requires_warranty' => true,
            'default_warranty_duration_unit' => 'months',
        ]);
        DB::table('products')->where('warranty_months', '>', 0)
            ->update(['default_warranty_duration_value' => DB::raw('warranty_months')]);
        DB::table('services')->where('default_warranty_months', '>', 0)->update([
            'requires_warranty' => true,
            'default_warranty_duration_unit' => 'months',
        ]);
        DB::table('services')->where('default_warranty_months', '>', 0)
            ->update(['default_warranty_duration_value' => DB::raw('default_warranty_months')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_shares');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('invoice_print_settings');
        });
        Schema::table('sales_invoice_items', function (Blueprint $table) {
            $table->dropIndex('invoice_item_warranty_index');
            $table->dropColumn(['warranty_applies', 'warranty_snapshot']);
        });
        foreach (['products', 'services', 'service_packages'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn([
                    'requires_warranty',
                    'default_warranty_film_type',
                    'default_warranty_duration_value',
                    'default_warranty_duration_unit',
                    'default_warranty_application_area',
                    'default_warranty_terms',
                    'default_warranty_notes',
                ]);
            });
        }
    }
};
