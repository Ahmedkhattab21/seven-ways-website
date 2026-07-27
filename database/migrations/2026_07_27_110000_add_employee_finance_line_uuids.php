<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['employee_commission_settlement_lines', 'employee_expense_claim_items'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'uuid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                });
            }
        }
    }

    public function down(): void
    {
        // Forward-only: employee finance history must never be altered automatically.
    }
};
