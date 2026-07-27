<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('scope_key', 180)->unique();
            $table->string('code', 50);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('swift_code', 20)->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_id')->constrained('banks')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('account_code', 50);
            $table->string('account_name');
            $table->text('iban')->nullable();
            $table->char('iban_hash', 64)->nullable();
            $table->string('account_number_masked', 50)->nullable();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('gl_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('bank_fees_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('interest_income_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('interest_expense_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('unidentified_receipts_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('unidentified_payments_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('account_type', 30)->default('current');
            $table->date('opening_date')->nullable();
            $table->date('closing_date')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('allows_receipts')->default(true);
            $table->boolean('allows_payments')->default(true);
            $table->boolean('allows_transfers')->default(true);
            $table->boolean('requires_reconciliation')->default(true);
            $table->date('last_reconciled_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'account_code']);
            $table->unique(['company_id', 'iban_hash']);
            $table->index(['company_id', 'status', 'currency_id']);
            $table->index(['company_id', 'gl_account_id']);
        });

        Schema::create('bank_account_branch_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->boolean('can_view')->default(true);
            $table->boolean('can_receive')->default(false);
            $table->boolean('can_pay')->default(false);
            $table->boolean('can_transfer')->default(false);
            $table->decimal('daily_payment_limit', 19, 4)->nullable();
            $table->decimal('daily_transfer_limit', 19, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['bank_account_id', 'branch_id']);
            $table->index(['company_id', 'branch_id', 'is_active'], 'bank_branch_access_lookup');
        });

        Schema::create('cash_boxes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->foreignId('gl_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('over_short_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_primary')->default(false);
            $table->boolean('allows_receipts')->default(true);
            $table->boolean('allows_payments')->default(true);
            $table->boolean('requires_shift_opening')->default(false);
            $table->decimal('maximum_cash_limit', 19, 4)->nullable();
            $table->decimal('minimum_cash_limit', 19, 4)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'gl_account_id']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('cash_box_custodians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_box_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('can_receive')->default(true);
            $table->boolean('can_pay')->default(false);
            $table->boolean('can_transfer')->default(false);
            $table->decimal('payment_limit', 19, 4)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'cash_box_id', 'is_active'], 'cash_custodian_lookup');
            $table->index(['company_id', 'user_id', 'valid_from', 'valid_to'], 'cash_custodian_user_dates');
        });

        Schema::create('treasury_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('document_number', 80);
            $table->string('from_type', 20);
            $table->foreignId('from_bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('from_cash_box_id')->nullable()->constrained('cash_boxes')->restrictOnDelete();
            $table->string('to_type', 20);
            $table->foreignId('to_bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('to_cash_box_id')->nullable()->constrained('cash_boxes')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('destination_branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate', 19, 8)->default(1);
            $table->decimal('amount', 19, 4);
            $table->decimal('fees_amount', 19, 4)->default(0);
            $table->string('status', 30)->default('draft');
            $table->date('transfer_date');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'document_number']);
            $table->index(['company_id', 'status', 'transfer_date']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::table('payment_method_account_mappings', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['account_id']);
        });
        Schema::table('payment_method_account_mappings', function (Blueprint $table) {
            $table->dropUnique('payment_method_branch_unique');
        });
        DB::statement('ALTER TABLE payment_method_account_mappings MODIFY branch_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE payment_method_account_mappings MODIFY account_id BIGINT UNSIGNED NULL');
        Schema::table('payment_method_account_mappings', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->after('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_box_id')->nullable()->after('bank_account_id')->constrained()->restrictOnDelete();
            $table->string('operation_type', 30)->default('receipt')->after('payment_method_id');
            $table->foreignId('clearing_account_id')->nullable()->after('cash_box_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('fees_account_id')->nullable()->after('clearing_account_id')->constrained('accounts')->restrictOnDelete();
            $table->unsignedSmallInteger('settlement_days')->nullable()->after('fees_account_id');
            $table->string('scope_key', 180)->nullable()->after('company_id');
            $table->unique('scope_key');
            $table->index(['company_id', 'payment_method_id', 'branch_id', 'operation_type'], 'treasury_mapping_lookup');
        });
        DB::table('payment_method_account_mappings')->orderBy('id')->each(function ($mapping) {
            DB::table('payment_method_account_mappings')->where('id', $mapping->id)->update([
                'scope_key' => implode(':', [
                    $mapping->company_id, $mapping->payment_method_id, $mapping->branch_id ?: 0, 'receipt',
                ]),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('payment_method_account_mappings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropConstrainedForeignId('cash_box_id');
            $table->dropConstrainedForeignId('clearing_account_id');
            $table->dropConstrainedForeignId('fees_account_id');
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['account_id']);
            $table->dropForeign(['payment_method_id']);
            $table->dropForeign(['company_id']);
        });
        Schema::table('payment_method_account_mappings', function (Blueprint $table) {
            $table->dropIndex('treasury_mapping_lookup');
            $table->dropUnique(['scope_key']);
            $table->dropColumn(['operation_type', 'settlement_days', 'scope_key']);
        });
        DB::statement('ALTER TABLE payment_method_account_mappings MODIFY branch_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE payment_method_account_mappings MODIFY account_id BIGINT UNSIGNED NOT NULL');
        Schema::table('payment_method_account_mappings', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->restrictOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->unique(['branch_id', 'payment_method_id'], 'payment_method_branch_unique');
        });
        Schema::dropIfExists('treasury_transfers');
        Schema::dropIfExists('cash_box_custodians');
        Schema::dropIfExists('cash_boxes');
        Schema::dropIfExists('bank_account_branch_access');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('banks');
    }
};
