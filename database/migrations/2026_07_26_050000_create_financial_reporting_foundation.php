<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_report_definitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('report_type', 30);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'report_type', 'is_active'], 'financial_report_definition_lookup');
        });

        Schema::create('financial_report_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_report_definition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_section_id')->nullable()->constrained('financial_report_sections')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('section_type', 30);
            $table->decimal('sign_multiplier', 8, 4)->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_total')->default(false);
            $table->string('formula')->nullable();
            $table->timestamps();
            $table->unique(['financial_report_definition_id', 'code'], 'financial_report_section_code_unique');
            $table->index(['financial_report_definition_id', 'parent_section_id', 'sort_order'], 'financial_report_section_tree');
        });

        Schema::create('financial_report_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('financial_report_section_id');
            $table->foreign('financial_report_section_id', 'fr_mapping_section_fk')
                ->references('id')->on('financial_report_sections')->cascadeOnDelete();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->foreign('account_id', 'fr_mapping_account_fk')->references('id')->on('accounts')->restrictOnDelete();
            $table->unsignedBigInteger('account_group_id')->nullable();
            $table->foreign('account_group_id', 'fr_mapping_group_fk')->references('id')->on('account_groups')->restrictOnDelete();
            $table->unsignedBigInteger('account_type_id')->nullable();
            $table->foreign('account_type_id', 'fr_mapping_type_fk')->references('id')->on('account_types')->restrictOnDelete();
            $table->decimal('sign_multiplier', 8, 4)->default(1);
            $table->timestamps();
            $table->index(['financial_report_section_id', 'account_id'], 'financial_mapping_account');
            $table->index(['financial_report_section_id', 'account_group_id'], 'financial_mapping_group');
            $table->index(['financial_report_section_id', 'account_type_id'], 'financial_mapping_type');
        });

        Schema::create('cash_flow_mappings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('account_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('cash_flow_category', 20);
            $table->string('cash_flow_line');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'cash_flow_category', 'is_active'], 'cash_flow_mapping_lookup');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index(['company_id', 'status', 'posting_date'], 'journals_reporting_date');
            $table->index(['company_id', 'fiscal_year_id', 'accounting_period_id', 'status'], 'journals_reporting_period');
            $table->index(['company_id', 'source_type', 'status'], 'journals_reporting_source');
        });
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->index(['account_id', 'journal_entry_id'], 'journal_lines_account_entry');
            $table->index(['branch_id', 'journal_entry_id'], 'journal_lines_branch_entry');
            $table->index(['cost_center_id', 'journal_entry_id'], 'journal_lines_cost_center_entry');
            $table->index(['customer_id', 'journal_entry_id'], 'journal_lines_customer_entry');
            $table->index(['supplier_id', 'journal_entry_id'], 'journal_lines_supplier_entry');
            $table->index(['product_id', 'journal_entry_id'], 'journal_lines_product_entry');
            $table->index(['warehouse_id', 'journal_entry_id'], 'journal_lines_warehouse_entry');
            $table->index(['currency_id', 'journal_entry_id'], 'journal_lines_currency_entry');
        });
    }

    public function down(): void
    {
        $existingIndexes = collect(DB::select('SHOW INDEX FROM journal_entry_lines'))->pluck('Key_name')->unique();
        foreach (['account_id', 'branch_id', 'cost_center_id', 'customer_id', 'supplier_id',
            'product_id', 'warehouse_id', 'currency_id'] as $column) {
            $index = "journal_entry_lines_{$column}_foreign";
            if (! $existingIndexes->contains($index)) {
                Schema::table('journal_entry_lines', function (Blueprint $table) use ($column, $index) {
                    $table->index($column, $index);
                });
            }
        }
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            foreach (['journal_lines_account_entry', 'journal_lines_branch_entry', 'journal_lines_cost_center_entry',
                'journal_lines_customer_entry', 'journal_lines_supplier_entry', 'journal_lines_product_entry',
                'journal_lines_warehouse_entry', 'journal_lines_currency_entry'] as $index) {
                $table->dropIndex($index);
            }
        });
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('journals_reporting_date');
            $table->dropIndex('journals_reporting_period');
            $table->dropIndex('journals_reporting_source');
        });
        Schema::dropIfExists('cash_flow_mappings');
        Schema::dropIfExists('financial_report_account_mappings');
        Schema::dropIfExists('financial_report_sections');
        Schema::dropIfExists('financial_report_definitions');
    }
};
