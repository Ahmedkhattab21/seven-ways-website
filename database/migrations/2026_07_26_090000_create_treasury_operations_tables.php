<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_transfers', function (Blueprint $table) {
            $table->string('transfer_type', 30)->default('transfer')->after('document_number');
            $table->foreignId('processed_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable()->after('approved_at');
            $table->timestamp('failed_at')->nullable()->after('processed_at');
            $table->text('failure_reason')->nullable()->after('failed_at');
            $table->foreignId('reversed_by')->nullable()->after('completed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('completed_at');
            $table->foreignId('reversal_journal_entry_id')->nullable()->after('journal_entry_id')
                ->constrained('journal_entries')->restrictOnDelete();
            $table->uuid('idempotency_key')->nullable()->after('reversal_journal_entry_id');
            $table->unique(['company_id', 'idempotency_key'], 'treasury_transfer_idempotency');
        });

        Schema::create('cash_box_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_box_id')->constrained()->restrictOnDelete();
            $table->foreignId('custodian_user_id')->constrained('users')->restrictOnDelete();
            $table->string('session_number', 80);
            $table->date('business_date');
            $table->string('status', 30)->default('draft');
            $table->string('active_guard', 10)->nullable();
            $table->decimal('opening_book_balance', 19, 4);
            $table->decimal('opening_counted_balance', 19, 4)->default(0);
            $table->decimal('opening_difference', 19, 4)->default(0);
            $table->decimal('closing_book_balance', 19, 4)->nullable();
            $table->decimal('closing_counted_balance', 19, 4)->nullable();
            $table->decimal('closing_difference', 19, 4)->nullable();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->foreignId('counting_started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counting_started_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'session_number']);
            $table->unique(['cash_box_id', 'active_guard'], 'cash_box_one_active_session');
            $table->index(['company_id', 'branch_id', 'status'], 'cash_session_scope');
        });

        Schema::create('cash_box_counts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_box_session_id')->constrained()->restrictOnDelete();
            $table->string('count_type', 20);
            $table->string('status', 30)->default('draft');
            $table->decimal('counted_total', 19, 4)->default(0);
            $table->decimal('book_total', 19, 4);
            $table->decimal('difference', 19, 4)->default(0);
            $table->foreignId('counted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'cash_box_session_id', 'count_type'], 'cash_count_lookup');
        });

        Schema::create('cash_box_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_box_count_id')->constrained()->restrictOnDelete();
            $table->decimal('denomination', 19, 4);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 19, 4);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['cash_box_count_id', 'denomination'], 'cash_count_denomination');
        });

        Schema::create('cash_over_short_adjustments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_box_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_box_count_id')->constrained()->restrictOnDelete();
            $table->string('adjustment_type', 20);
            $table->decimal('amount', 19, 4);
            $table->string('status', 30)->default('draft');
            $table->text('description');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->timestamps();
            $table->unique('cash_box_count_id');
            $table->index(['company_id', 'status']);
        });

        $this->createCashOperationTable('cash_receipts', 'receipt_type');
        $this->createCashOperationTable('cash_payments', 'payment_type');

        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('direction', 10);
            $table->string('cheque_number', 100);
            $table->string('cheque_scope_key', 191)->unique();
            $table->foreignId('bank_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('drawer_name')->nullable();
            $table->string('drawer_bank_name')->nullable();
            $table->string('beneficiary_name')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->date('issue_date');
            $table->date('due_date');
            $table->date('received_date')->nullable();
            $table->date('deposit_date')->nullable();
            $table->date('clearance_date')->nullable();
            $table->date('bounce_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('document_number', 80);
            $table->foreignId('clearing_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('bank_gl_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('offset_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('clearance_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('bounce_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deposited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('bounced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'document_number']);
            $table->index(['company_id', 'branch_id', 'direction', 'status'], 'cheque_register');
            $table->index(['company_id', 'due_date', 'status'], 'cheque_aging');
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('cheque_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('cheque_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['company_id', 'cheque_id', 'changed_at'], 'cheque_history_lookup');
        });

        Schema::create('cheque_endorsements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('cheque_id')->constrained()->restrictOnDelete();
            $table->string('endorsed_to_type', 30);
            $table->unsignedBigInteger('endorsed_to_id')->nullable();
            $table->string('endorsed_to_name');
            $table->date('endorsement_date');
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'cheque_id', 'status'], 'cheque_endorsement_lookup');
        });

        Schema::create('merchant_settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('document_number', 80);
            $table->string('settlement_reference');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('settlement_date');
            $table->decimal('gross_amount', 19, 4);
            $table->decimal('fees_amount', 19, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('net_amount', 19, 4);
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->uuid('idempotency_key')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'document_number']);
            $table->unique(['company_id', 'settlement_reference']);
            $table->unique(['company_id', 'idempotency_key'], 'merchant_settlement_idempotency');
            $table->index(['company_id', 'status', 'settlement_date'], 'merchant_settlement_register');
        });

        Schema::create('merchant_settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_settlement_id')->constrained()->restrictOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('source_reference')->nullable();
            $table->decimal('gross_amount', 19, 4);
            $table->decimal('allocated_amount', 19, 4);
            $table->decimal('fees_share', 19, 4)->default(0);
            $table->decimal('net_amount', 19, 4);
            $table->timestamps();
            $table->unique(['merchant_settlement_id', 'source_type', 'source_id'], 'merchant_source_unique');
            $table->index(['source_type', 'source_id'], 'merchant_source_lookup');
        });

        Schema::create('treasury_approval_limits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('operation_type', 40);
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('minimum_amount', 19, 4)->default(0);
            $table->decimal('maximum_amount', 19, 4)->nullable();
            $table->unsignedSmallInteger('approval_level')->default(1);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_submit')->default(false);
            $table->boolean('can_approve')->default(false);
            $table->boolean('can_post')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(
                ['company_id', 'operation_type', 'currency_id', 'is_active'],
                'treasury_limit_lookup'
            );
            $table->index(['user_id', 'role_id', 'branch_id'], 'treasury_limit_subject');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_approval_limits');
        Schema::dropIfExists('merchant_settlement_lines');
        Schema::dropIfExists('merchant_settlements');
        Schema::dropIfExists('cheque_endorsements');
        Schema::dropIfExists('cheque_status_histories');
        Schema::dropIfExists('cheques');
        Schema::dropIfExists('cash_payments');
        Schema::dropIfExists('cash_receipts');
        Schema::dropIfExists('cash_over_short_adjustments');
        Schema::dropIfExists('cash_box_count_lines');
        Schema::dropIfExists('cash_box_counts');
        Schema::dropIfExists('cash_box_sessions');

        Schema::table('treasury_transfers', function (Blueprint $table) {
            $table->dropUnique('treasury_transfer_idempotency');
            $table->dropConstrainedForeignId('processed_by');
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropConstrainedForeignId('reversal_journal_entry_id');
            $table->dropColumn([
                'transfer_type', 'processed_at', 'failed_at', 'failure_reason',
                'reversed_at', 'idempotency_key',
            ]);
        });
    }

    private function createCashOperationTable(string $name, string $typeColumn): void
    {
        Schema::create($name, function (Blueprint $table) use ($name, $typeColumn) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_box_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_box_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('document_number', 80);
            $table->string($typeColumn, 40);
            $table->string('status', 30)->default('draft');
            $table->date('document_date');
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate', 19, 8)->default(1);
            $table->decimal('amount', 19, 4);
            $table->foreignId('offset_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('description');
            $table->string('reference')->nullable();
            $table->uuid('idempotency_key')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'document_number']);
            $table->unique(['company_id', 'idempotency_key'], $name.'_idempotency');
            $table->index(['company_id', 'branch_id', 'status', 'document_date'], $name.'_register');
        });
    }
};
