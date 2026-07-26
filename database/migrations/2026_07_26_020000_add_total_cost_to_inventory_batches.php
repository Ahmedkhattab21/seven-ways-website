<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->decimal('total_cost', 19, 4)->default(0)->after('available_quantity');
        });
        Schema::table('supplier_credit_notes', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->after('supplier_id')
                ->constrained()->restrictOnDelete();
        });

        DB::table('inventory_batches')->update([
            'total_cost' => DB::raw('ROUND(received_quantity * unit_cost, 4)'),
        ]);
        DB::statement('
            UPDATE supplier_credit_notes credit
            LEFT JOIN supplier_invoices invoice ON invoice.id = credit.supplier_invoice_id
            LEFT JOIN suppliers supplier ON supplier.id = credit.supplier_id
            LEFT JOIN companies company ON company.id = credit.company_id
            SET credit.currency_id = COALESCE(invoice.currency_id, supplier.currency_id, company.currency_id)
        ');
    }

    public function down(): void
    {
        Schema::table('supplier_credit_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
        });
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropColumn('total_cost');
        });
    }
};
