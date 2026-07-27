<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_commission_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('payable_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('rule_type', 40);
            $table->decimal('rule_value', 19, 4);
            $table->decimal('minimum_amount', 19, 4)->nullable();
            $table->decimal('maximum_amount', 19, 4)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(
                ['company_id', 'is_active', 'effective_from', 'effective_to'],
                'employee_commission_rule_resolution'
            );
            $table->index(
                ['company_id', 'employee_id', 'branch_id', 'product_id', 'service_id'],
                'employee_commission_rule_scope'
            );
        });

        Schema::create('employee_commission_accruals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('commission_rule_id')->constrained('employee_commission_rules')->restrictOnDelete();
            $table->foreignId('sales_invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sales_invoice_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('adjusts_accrual_id')->nullable()
                ->constrained('employee_commission_accruals')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('accounting_period_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('reversal_journal_entry_id')->nullable();
            $table->char('source_key', 64);
            $table->date('accrual_date');
            $table->decimal('basis_amount', 19, 4);
            $table->decimal('rule_value', 19, 4);
            $table->decimal('commission_amount', 19, 4);
            $table->decimal('settled_amount', 19, 4)->default(0);
            $table->json('calculation_snapshot');
            $table->string('status', 30)->default('calculated');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->foreign('reversal_journal_entry_id', 'commission_accrual_reversal_journal_fk')
                ->references('id')->on('journal_entries')->restrictOnDelete();
            $table->unique(['company_id', 'source_key'], 'employee_commission_accrual_source_unique');
            $table->index(['company_id', 'employee_id', 'status', 'accrual_date'], 'employee_commission_register');
        });

        Schema::create('employee_commission_settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('settlement_number', 80);
            $table->date('settlement_date');
            $table->decimal('total_amount', 19, 4);
            $table->string('status', 30)->default('draft');
            $table->foreignId('cash_payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('reversal_journal_entry_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('reversal_journal_entry_id', 'commission_settlement_reversal_journal_fk')
                ->references('id')->on('journal_entries')->restrictOnDelete();
            $table->unique(['company_id', 'settlement_number'], 'commission_settlement_number_unique');
            $table->index(['company_id', 'employee_id', 'status', 'settlement_date'], 'commission_settlement_register');
        });

        Schema::create('employee_commission_settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('commission_settlement_id');
            $table->unsignedBigInteger('commission_accrual_id');
            $table->decimal('amount', 19, 4);
            $table->timestamps();
            $table->foreign('commission_settlement_id', 'commission_line_settlement_fk')
                ->references('id')->on('employee_commission_settlements')->restrictOnDelete();
            $table->foreign('commission_accrual_id', 'commission_line_accrual_fk')
                ->references('id')->on('employee_commission_accruals')->restrictOnDelete();
            $table->unique(
                ['commission_settlement_id', 'commission_accrual_id'],
                'commission_settlement_accrual_unique'
            );
        });

        Schema::create('employee_expense_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->foreignId('expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('tax_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code'], 'employee_expense_category_code_unique');
        });

        Schema::create('employee_expense_claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('payable_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('claim_number', 80);
            $table->date('claim_date');
            $table->string('business_purpose');
            $table->text('description')->nullable();
            $table->decimal('subtotal', 19, 4);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('total_amount', 19, 4);
            $table->string('status', 30)->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('cash_payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'claim_number'], 'employee_expense_claim_number_unique');
            $table->index(['company_id', 'employee_id', 'status', 'claim_date'], 'employee_expense_register');
        });

        Schema::create('employee_expense_claim_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('expense_claim_id');
            $table->unsignedBigInteger('expense_category_id')->nullable();
            $table->foreignId('expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('tax_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('expense_date');
            $table->string('description');
            $table->decimal('net_amount', 19, 4);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('tax_amount', 19, 4)->default(0);
            $table->decimal('total_amount', 19, 4);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('expense_claim_id', 'expense_item_claim_fk')
                ->references('id')->on('employee_expense_claims')->restrictOnDelete();
            $table->foreign('expense_category_id', 'expense_item_category_fk')
                ->references('id')->on('employee_expense_categories')->restrictOnDelete();
        });

        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('receivable_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('advance_number', 80);
            $table->string('advance_type', 20);
            $table->date('request_date');
            $table->text('purpose');
            $table->decimal('amount', 19, 4);
            $table->decimal('settled_amount', 19, 4)->default(0);
            $table->string('status', 30)->default('draft');
            $table->foreignId('cash_payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'advance_number'], 'employee_advance_number_unique');
            $table->index(['company_id', 'employee_id', 'status', 'request_date'], 'employee_advance_register');
        });

        Schema::create('employee_advance_settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('employee_advance_id');
            $table->unsignedBigInteger('expense_claim_id')->nullable();
            $table->foreignId('cash_receipt_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('settlement_type', 30);
            $table->date('settlement_date');
            $table->decimal('amount', 19, 4);
            $table->string('status', 20)->default('posted');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->foreign('employee_advance_id', 'advance_settlement_advance_fk')
                ->references('id')->on('employee_advances')->restrictOnDelete();
            $table->foreign('expense_claim_id', 'advance_settlement_claim_fk')
                ->references('id')->on('employee_expense_claims')->restrictOnDelete();
            $table->unique(
                ['employee_advance_id', 'expense_claim_id', 'cash_receipt_id'],
                'employee_advance_settlement_source_unique'
            );
        });
    }

    public function down(): void
    {
        // Forward-only: employee finance history must never be dropped automatically.
    }
};
