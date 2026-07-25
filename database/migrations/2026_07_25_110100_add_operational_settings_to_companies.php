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
            $table->foreignId('currency_id')->nullable()->after('currency_code')->constrained('currencies')->restrictOnDelete();
            $table->string('date_format', 20)->default('Y-m-d')->after('fiscal_year_start_month');
            $table->string('time_format', 10)->default('H:i');
            $table->unsignedTinyInteger('money_decimal_places')->default(2);
            $table->string('default_language', 5)->default('ar');
            $table->string('ui_direction', 3)->default('rtl');
            $table->foreignId('default_tax_id')->nullable()->constrained('taxes')->nullOnDelete();
        });

        DB::table('companies')->orderBy('id')->each(function ($company) {
            $currencyId = DB::table('currencies')->where('code', $company->currency_code ?: 'SAR')->value('id');
            DB::table('companies')->where('id', $company->id)->update(['currency_id' => $currencyId]);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['default_tax_id']);
            $table->dropForeign(['currency_id']);
            $table->dropColumn([
                'currency_id', 'date_format', 'time_format', 'money_decimal_places',
                'default_language', 'ui_direction', 'default_tax_id',
            ]);
        });
    }
};
