<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_import_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('format', 20)->default('csv');
            $table->string('delimiter', 4)->default(',');
            $table->string('encoding', 20)->default('UTF-8');
            $table->string('date_format', 40)->default('Y-m-d');
            $table->string('decimal_separator', 2)->default('.');
            $table->string('thousands_separator', 2)->nullable();
            $table->boolean('has_header')->default(true);
            $table->json('column_mapping');
            $table->unsignedSmallInteger('skip_rows')->default(0);
            $table->string('direction_policy', 30)->default('credit_increases');
            $table->decimal('balance_tolerance', 19, 4)->default(0);
            $table->string('default_scope_key', 180)->nullable()->unique();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'bank_account_id', 'format', 'is_active'], 'bs_profile_lookup');
        });

        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->string('file_name');
            $table->string('original_file_name');
            $table->string('storage_path');
            $table->char('file_hash', 64);
            $table->string('format', 20)->default('csv');
            $table->string('parser_version', 30)->default('csv-v1');
            $table->string('statement_reference')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('opening_balance', 19, 4);
            $table->decimal('closing_balance', 19, 4);
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('uploaded');
            $table->unsignedInteger('total_lines')->default(0);
            $table->unsignedInteger('imported_lines')->default(0);
            $table->unsignedInteger('duplicate_lines')->default(0);
            $table->unsignedInteger('failed_lines')->default(0);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'bank_account_id', 'file_hash'], 'bs_import_file_unique');
            $table->index(['company_id', 'bank_account_id', 'status'], 'bs_import_scope_status');
            $table->index(['bank_account_id', 'period_start', 'period_end'], 'bs_import_period');
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_statement_import_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->string('bank_reference')->nullable();
            $table->string('external_id')->nullable();
            $table->text('description');
            $table->decimal('debit_amount', 19, 4)->default(0);
            $table->decimal('credit_amount', 19, 4)->default(0);
            $table->decimal('running_balance', 19, 4)->nullable();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('counterparty_name')->nullable();
            $table->text('counterparty_iban_encrypted')->nullable();
            $table->char('counterparty_iban_hash', 64)->nullable();
            $table->string('counterparty_iban_last4', 4)->nullable();
            $table->string('transaction_code', 50)->nullable();
            $table->string('status', 30)->default('unmatched');
            $table->decimal('matched_amount', 19, 4)->default(0);
            $table->decimal('unmatched_amount', 19, 4);
            $table->boolean('is_duplicate')->default(false);
            $table->foreignId('duplicate_of_id')->nullable()->constrained('bank_statement_lines')->restrictOnDelete();
            $table->char('raw_hash', 64);
            $table->json('raw_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->text('ignore_reason')->nullable();
            $table->foreignId('ignored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ignored_at')->nullable();
            $table->timestamps();
            $table->unique(['bank_statement_import_id', 'line_number'], 'bs_line_import_number_unique');
            $table->index(['company_id', 'bank_account_id', 'status', 'transaction_date'], 'bs_line_scope_status_date');
            $table->index(['bank_account_id', 'external_id'], 'bs_line_external');
            $table->index(['bank_account_id', 'bank_reference'], 'bs_line_reference');
            $table->index(['bank_account_id', 'raw_hash'], 'bs_line_raw_hash');
        });

        Schema::create('bank_reconciliation_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('accounting_period_id')->constrained()->restrictOnDelete();
            $table->string('session_number', 80);
            $table->date('date_from');
            $table->date('date_to');
            $table->decimal('statement_opening_balance', 19, 4);
            $table->decimal('statement_closing_balance', 19, 4);
            $table->decimal('book_opening_balance', 19, 4);
            $table->decimal('book_closing_balance', 19, 4);
            $table->decimal('matched_statement_amount', 19, 4)->default(0);
            $table->decimal('matched_book_amount', 19, 4)->default(0);
            $table->decimal('unreconciled_statement_amount', 19, 4)->default(0);
            $table->decimal('unreconciled_book_amount', 19, 4)->default(0);
            $table->decimal('difference', 19, 4)->default(0);
            $table->decimal('tolerance', 19, 4)->default(0);
            $table->string('status', 30)->default('draft');
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reason')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'session_number'], 'bank_recon_session_number_unique');
            $table->index(['company_id', 'bank_account_id', 'status'], 'bank_recon_scope_status');
            $table->index(['bank_account_id', 'date_from', 'date_to'], 'bank_recon_dates');
        });

        Schema::create('bank_reconciliation_session_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('bank_reconciliation_session_id');
            $table->unsignedBigInteger('bank_statement_import_id');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['bank_reconciliation_session_id', 'bank_statement_import_id'], 'bank_recon_session_import_unique');
            $table->index(['bank_statement_import_id'], 'bank_recon_import_lookup');
            $table->foreign('bank_reconciliation_session_id', 'brsi_session_fk')
                ->references('id')->on('bank_reconciliation_sessions')->restrictOnDelete();
            $table->foreign('bank_statement_import_id', 'brsi_import_fk')
                ->references('id')->on('bank_statement_imports')->restrictOnDelete();
        });

        Schema::create('bank_reconciliation_matches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('bank_reconciliation_session_id');
            $table->string('match_type', 30);
            $table->string('status', 20)->default('suggested');
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->string('match_method', 20)->default('manual');
            $table->decimal('matched_amount', 19, 4);
            $table->decimal('difference_amount', 19, 4)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'bank_reconciliation_session_id', 'status'], 'bank_recon_match_status');
            $table->foreign('bank_reconciliation_session_id', 'brm_session_fk')
                ->references('id')->on('bank_reconciliation_sessions')->restrictOnDelete();
        });

        Schema::create('bank_matching_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('priority')->default(100);
            $table->string('condition_type', 40);
            $table->string('condition_value')->nullable();
            $table->decimal('amount_min', 19, 4)->nullable();
            $table->decimal('amount_max', 19, 4)->nullable();
            $table->string('direction', 20)->nullable();
            $table->string('transaction_code', 50)->nullable();
            $table->string('result_type', 40);
            $table->foreignId('suggested_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('suggested_customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('suggested_supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->string('suggested_operation_type', 40)->nullable();
            $table->boolean('auto_match')->default(false);
            $table->unsignedTinyInteger('minimum_confidence')->default(90);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'bank_account_id', 'is_active', 'priority'], 'bank_match_rule_lookup');
        });

        Schema::create('bank_adjustments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('bank_reconciliation_session_id')->nullable();
            $table->foreignId('bank_statement_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('document_number', 80);
            $table->string('adjustment_type', 40);
            $table->string('status', 30)->default('draft');
            $table->date('adjustment_date');
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate', 19, 8)->default(1);
            $table->decimal('amount', 19, 4);
            $table->foreignId('offset_account_id')->constrained('accounts')->restrictOnDelete();
            $table->text('description');
            $table->string('reference')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'document_number'], 'bank_adjustment_number_unique');
            $table->index(['company_id', 'bank_account_id', 'status', 'adjustment_date'], 'bank_adjustment_lookup');
            $table->index(['bank_reconciliation_session_id', 'bank_statement_line_id'], 'bank_adjustment_recon_line');
            $table->foreign('bank_reconciliation_session_id', 'ba_session_fk')
                ->references('id')->on('bank_reconciliation_sessions')->restrictOnDelete();
        });

        Schema::create('bank_reconciliation_match_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_reconciliation_match_id');
            $table->string('side', 20);
            $table->foreignId('statement_line_id')->nullable()->constrained('bank_statement_lines')->restrictOnDelete();
            $table->foreignId('journal_entry_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('bank_adjustment_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('allocated_amount', 19, 4);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['statement_line_id'], 'bank_match_statement_line');
            $table->index(['journal_entry_line_id'], 'bank_match_journal_line');
            $table->index(['bank_adjustment_id'], 'bank_match_adjustment');
            $table->foreign('bank_reconciliation_match_id', 'brmi_match_fk')
                ->references('id')->on('bank_reconciliation_matches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_match_items');
        Schema::dropIfExists('bank_adjustments');
        Schema::dropIfExists('bank_matching_rules');
        Schema::dropIfExists('bank_reconciliation_matches');
        Schema::dropIfExists('bank_reconciliation_session_imports');
        Schema::dropIfExists('bank_reconciliation_sessions');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statement_imports');
        Schema::dropIfExists('bank_statement_import_profiles');
    }
};
