<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('accounting_period_id')->constrained()->restrictOnDelete();
            $table->string('journal_number', 80);
            $table->string('entry_type', 40)->default('manual');
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->uuid('source_uuid')->nullable();
            $table->string('source_number', 100)->nullable();
            $table->foreignId('posting_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('reversed_by_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->date('entry_date');
            $table->date('posting_date')->nullable();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate', 19, 8)->default(1);
            $table->text('description');
            $table->string('reference', 150)->nullable();
            $table->decimal('total_debit', 19, 4)->default(0);
            $table->decimal('total_credit', 19, 4)->default(0);
            $table->decimal('base_total_debit', 19, 4)->default(0);
            $table->decimal('base_total_credit', 19, 4)->default(0);
            $table->boolean('is_automatic')->default(false);
            $table->boolean('is_reversal')->default(false);
            $table->boolean('is_opening')->default(false);
            $table->boolean('is_adjusting')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'journal_number']);
            $table->index(['company_id', 'branch_id', 'status', 'entry_date'], 'journals_scope_index');
            $table->index(['company_id', 'source_type', 'source_id'], 'journals_source_index');
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('tax_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate', 19, 8)->default(1);
            $table->decimal('debit_amount', 19, 4)->default(0);
            $table->decimal('credit_amount', 19, 4)->default(0);
            $table->decimal('base_debit_amount', 19, 4)->default(0);
            $table->decimal('base_credit_amount', 19, 4)->default(0);
            $table->string('tax_component', 20)->default('none');
            $table->text('description')->nullable();
            $table->string('reference', 150)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['journal_entry_id', 'line_number']);
        });

        Schema::create('accounting_posting_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source_type', 80);
            $table->unsignedBigInteger('source_id');
            $table->uuid('source_uuid')->nullable();
            $table->string('posting_action', 50)->default('post');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 180)->unique();
            $table->string('status', 30)->default('posted');
            $table->foreignId('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'source_type', 'source_id', 'posting_action'], 'posting_links_source_action_unique');
        });

        Schema::create('payment_method_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'payment_method_id'], 'payment_method_branch_unique');
        });

        Schema::create('product_accounting_mappings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('revenue_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('cogs_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('purchase_return_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('adjustment_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'product_id']);
        });

        Schema::table('opening_balance_documents', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')->nullable()->after('approved_by')->constrained()->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('posted_at');
        });
    }

    public function down(): void
    {
        Schema::table('opening_balance_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journal_entry_id');
            $table->dropColumn('reversed_at');
        });
        Schema::dropIfExists('product_accounting_mappings');
        Schema::dropIfExists('payment_method_account_mappings');
        Schema::dropIfExists('accounting_posting_links');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
    }
};
