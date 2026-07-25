<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('stock_transfers', 'deleted_at')) {
            Schema::table('stock_transfers', fn (Blueprint $table) => $table->dropColumn('deleted_at'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('stock_transfers', 'deleted_at')) {
            Schema::table('stock_transfers', fn (Blueprint $table) => $table->softDeletes());
        }
    }
};
