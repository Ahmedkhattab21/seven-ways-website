<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->string('code', 50)->default('')->after('company_id');
            $table->foreignId('opened_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable()->after('opened_by');
            $table->foreignId('reopened_by')->nullable()->after('closed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable()->after('reopened_by');
            $table->text('close_notes')->nullable()->after('reopened_at');
        });
        DB::table('fiscal_years')->orderBy('id')->get(['id', 'start_date'])->each(
            fn ($year) => DB::table('fiscal_years')->where('id', $year->id)->update([
                'code' => 'FY-'.substr((string) $year->start_date, 0, 4).'-'.$year->id,
            ])
        );
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->unique(['company_id', 'code'], 'fiscal_years_company_code_unique');
            $table->index(['company_id', 'status'], 'fiscal_years_company_status_index');
        });

        Schema::create('account_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('classification', 30);
            $table->string('normal_balance', 10);
            $table->string('statement_type', 30);
            $table->string('cash_flow_category', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'classification', 'is_active']);
        });

        Schema::create('account_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_group_id')->nullable()->constrained('account_groups')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('level')->default(0);
            $table->string('path', 1000)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'parent_group_id', 'is_active'], 'account_groups_tree_index');
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parent_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->string('account_code', 50);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('account_level')->default(0);
            $table->string('account_path', 1000)->nullable();
            $table->boolean('is_header')->default(false);
            $table->boolean('is_posting')->default(true);
            $table->string('normal_balance', 10);
            $table->foreignId('currency_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('allows_multi_currency')->default(false);
            $table->boolean('requires_cost_center')->default(false);
            $table->boolean('requires_branch')->default(false);
            $table->boolean('requires_customer')->default(false);
            $table->boolean('requires_supplier')->default(false);
            $table->boolean('requires_employee')->default(false);
            $table->boolean('requires_vehicle')->default(false);
            $table->boolean('is_control_account')->default(false);
            $table->string('control_type', 40)->nullable();
            $table->boolean('is_bank_account')->default(false);
            $table->boolean('is_cash_account')->default(false);
            $table->boolean('is_inventory_account')->default(false);
            $table->boolean('is_tax_account')->default(false);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_manual_entry')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'account_code']);
            $table->index(['company_id', 'parent_account_id', 'is_active'], 'accounts_tree_index');
            $table->index(['company_id', 'account_type_id', 'account_group_id'], 'accounts_classification_index');
            $table->index(['company_id', 'control_type', 'is_active'], 'accounts_control_index');
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('period_number');
            $table->string('code', 50);
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open');
            $table->boolean('is_adjustment_period')->default(false);
            $table->json('locked_modules')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('close_reason')->nullable();
            $table->timestamps();
            $table->unique(['fiscal_year_id', 'period_number']);
            $table->unique(['company_id', 'fiscal_year_id', 'code'], 'accounting_periods_company_year_code_unique');
            $table->index(['company_id', 'status', 'start_date', 'end_date'], 'accounting_periods_lookup_index');
        });

        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parent_cost_center_id')->nullable()->constrained('cost_centers')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('level')->default(0);
            $table->string('path', 1000)->nullable();
            $table->string('cost_center_type', 30);
            $table->boolean('is_header')->default(false);
            $table->boolean('is_posting')->default(true);
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'parent_cost_center_id', 'is_active'], 'cost_centers_tree_index');
            $table->index(['company_id', 'branch_id', 'cost_center_type'], 'cost_centers_scope_index');
        });

        Schema::create('accounting_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('current_fiscal_year_id')->nullable()->constrained('fiscal_years')->restrictOnDelete();
            $table->unsignedTinyInteger('default_rounding_precision')->default(4);
            $table->boolean('allow_manual_journals')->default(false);
            $table->boolean('require_journal_approval')->default(true);
            $table->boolean('enforce_balanced_dimensions')->default(true);
            $table->boolean('enforce_cost_center_on_expense')->default(false);
            $table->boolean('enforce_branch_on_posting')->default(true);
            $table->boolean('allow_posting_to_soft_closed_period')->default(false);
            $table->boolean('separation_of_duties')->default(true);
            $table->boolean('auto_post_sales')->default(false);
            $table->boolean('auto_post_purchases')->default(false);
            $table->boolean('auto_post_inventory')->default(false);
            $table->boolean('auto_post_payments')->default(false);
            $table->timestamps();
        });

        Schema::create('branch_accounting_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('default_cost_center_id')->nullable()->constrained('cost_centers')->restrictOnDelete();
            foreach ([
                'cash_account_id', 'bank_account_id', 'accounts_receivable_account_id',
                'accounts_payable_account_id', 'sales_revenue_account_id', 'service_revenue_account_id',
                'product_revenue_account_id', 'sales_discount_account_id', 'sales_return_account_id',
                'inventory_account_id', 'cost_of_goods_sold_account_id', 'inventory_adjustment_account_id',
                'purchase_account_id', 'purchase_return_account_id', 'vat_input_account_id',
                'vat_output_account_id', 'customer_advance_account_id', 'supplier_advance_account_id',
                'rounding_account_id',
            ] as $column) {
                $table->unsignedBigInteger($column)->nullable();
                $table->foreign($column, 'branch_acc_'.str_replace('_account_id', '', $column).'_fk')
                    ->references('id')->on('accounts')->restrictOnDelete();
            }
            $table->timestamps();
            $table->index(['company_id', 'branch_id']);
        });

        Schema::create('posting_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('source_type', 50);
            $table->text('description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code', 'version']);
            $table->index(['company_id', 'source_type', 'status', 'is_default'], 'posting_profiles_lookup_index');
            $table->index(['effective_from', 'effective_to']);
        });

        Schema::create('posting_profile_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('posting_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->string('entry_side', 10);
            $table->string('account_source', 40);
            $table->foreignId('fixed_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->string('amount_source', 30);
            $table->string('description_template')->nullable();
            $table->boolean('requires_customer')->default(false);
            $table->boolean('requires_supplier')->default(false);
            $table->boolean('requires_product')->default(false);
            $table->boolean('requires_branch')->default(false);
            $table->boolean('requires_cost_center')->default(false);
            $table->string('tax_component', 10)->default('none');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['posting_profile_id', 'line_number']);
        });

        Schema::create('opening_balance_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->restrictOnDelete();
            $table->string('document_number', 60);
            $table->string('status', 30)->default('draft');
            $table->date('balance_date');
            $table->text('description')->nullable();
            $table->decimal('total_debit', 19, 4)->default(0);
            $table->decimal('total_credit', 19, 4)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'document_number']);
            $table->index(['company_id', 'branch_id', 'status', 'balance_date'], 'opening_balances_scope_index');
        });

        Schema::create('opening_balance_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate', 19, 8)->default(1);
            $table->decimal('debit_amount', 19, 4)->default(0);
            $table->decimal('credit_amount', 19, 4)->default(0);
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['opening_balance_document_id', 'sort_order'], 'opening_balance_lines_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_lines');
        Schema::dropIfExists('opening_balance_documents');
        Schema::dropIfExists('posting_profile_lines');
        Schema::dropIfExists('posting_profiles');
        Schema::dropIfExists('branch_accounting_settings');
        Schema::dropIfExists('accounting_settings');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('account_groups');
        Schema::dropIfExists('account_types');

        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropUnique('fiscal_years_company_code_unique');
            $table->dropIndex('fiscal_years_company_status_index');
            $table->dropConstrainedForeignId('opened_by');
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn(['code', 'opened_at', 'reopened_at', 'close_notes']);
        });
    }
};
