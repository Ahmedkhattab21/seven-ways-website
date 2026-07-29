<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_taxable')->nullable()->after('default_tax_id');
            $table->string('opening_balances_decision', 30)->default('pending')->after('is_taxable');
        });

        DB::table('companies')
            ->whereIn('id', DB::table('opening_balance_documents')->where('status', 'posted')->select('company_id'))
            ->update(['opening_balances_decision' => 'entered']);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['is_taxable', 'opening_balances_decision']);
        });
    }
};
