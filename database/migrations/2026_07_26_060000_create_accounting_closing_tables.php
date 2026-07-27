<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_closing_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('accounting_period_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('closing_type', 40);
            $table->string('run_number', 80);
            $table->string('status', 30)->default('draft');
            $table->string('active_key', 10)->nullable()->default('active');
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
            $table->text('failure_reason')->nullable();
            $table->json('validation_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'run_number']);
            $table->unique(['company_id', 'fiscal_year_id', 'accounting_period_id', 'closing_type', 'active_key'], 'closing_run_active_unique');
            $table->index(['company_id', 'status', 'closing_type'], 'closing_run_lookup');
        });

        Schema::create('accounting_closing_checklists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('accounting_period_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('closing_run_id')->unique()->constrained('accounting_closing_runs')->restrictOnDelete();
            $table->string('checklist_type', 20);
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('completed_items')->default(0);
            $table->unsignedSmallInteger('total_items')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_closing_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('accounting_closing_checklists')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('category', 30);
            $table->string('severity', 20);
            $table->string('status', 20)->default('pending');
            $table->boolean('is_required')->default(true);
            $table->boolean('is_automated')->default(true);
            $table->text('result_summary')->nullable();
            $table->text('blocking_reason')->nullable();
            $table->json('evidence')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['checklist_id', 'code']);
            $table->index(['checklist_id', 'status', 'severity'], 'closing_check_item_lookup');
        });

        Schema::create('accounting_closing_exceptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('closing_run_id')->constrained('accounting_closing_runs')->restrictOnDelete();
            $table->string('exception_type', 80);
            $table->string('severity', 20);
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 19, 4)->nullable();
            $table->text('description');
            $table->string('status', 20)->default('open');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('waived_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'closing_run_id', 'status', 'severity'], 'closing_exception_lookup');
        });

        Schema::create('accounting_adjustments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('journal_entry_id')->unique()->constrained()->restrictOnDelete();
            $table->string('adjustment_type', 30);
            $table->string('supporting_reference')->nullable();
            $table->string('reversal_policy', 20)->default('none');
            $table->date('scheduled_reversal_date')->nullable();
            $table->foreignId('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'status', 'adjustment_type'], 'adjustment_lookup');
        });

        Schema::create('scheduled_journal_reversals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('original_journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->date('scheduled_date');
            $table->foreignId('target_fiscal_year_id')->nullable()->constrained('fiscal_years')->restrictOnDelete();
            $table->foreignId('target_accounting_period_id')->nullable()->constrained('accounting_periods')->restrictOnDelete();
            $table->string('status', 20)->default('scheduled');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->text('failure_reason')->nullable();
            $table->string('idempotency_key', 180)->unique();
            $table->timestamps();
            $table->index(['company_id', 'status', 'scheduled_date'], 'scheduled_reversal_lookup');
        });

        Schema::create('year_end_closing_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('income_summary_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('retained_earnings_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('current_year_profit_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->unsignedBigInteger('opening_balance_equity_account_id')->nullable();
            $table->foreign('opening_balance_equity_account_id', 'year_close_opening_equity_fk')
                ->references('id')->on('accounts')->restrictOnDelete();
            $table->boolean('close_revenue_directly_to_retained_earnings')->default(false);
            $table->boolean('create_opening_journal')->default(true);
            $table->boolean('auto_create_next_fiscal_year')->default(true);
            $table->boolean('auto_generate_next_periods')->default(true);
            $table->boolean('lock_year_after_close')->default(false);
            $table->boolean('require_all_reconciliations')->default(true);
            foreach (['reconciliation', 'trial_balance', 'ar_reconciliation', 'ap_reconciliation',
                'inventory_reconciliation', 'vat_reconciliation', 'cash_reconciliation'] as $name) {
                $table->decimal($name.'_tolerance', 19, 4)->default(0);
            }
            $table->timestamps();
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('closing_subtype', 40)->nullable()->after('entry_type');
            $table->foreignId('closing_run_id')->nullable()->after('closing_subtype')
                ->constrained('accounting_closing_runs')->restrictOnDelete();
            $table->index(['company_id', 'closing_run_id', 'closing_subtype'], 'journal_closing_lookup');
        });
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->foreignId('closing_run_id')->nullable()->constrained('accounting_closing_runs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_years', fn (Blueprint $table) => $table->dropConstrainedForeignId('closing_run_id'));
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('journal_closing_lookup');
            $table->dropConstrainedForeignId('closing_run_id');
            $table->dropColumn('closing_subtype');
        });
        Schema::dropIfExists('year_end_closing_settings');
        Schema::dropIfExists('scheduled_journal_reversals');
        Schema::dropIfExists('accounting_adjustments');
        Schema::dropIfExists('accounting_closing_exceptions');
        Schema::dropIfExists('accounting_closing_checklist_items');
        Schema::dropIfExists('accounting_closing_checklists');
        Schema::dropIfExists('accounting_closing_runs');
    }
};
