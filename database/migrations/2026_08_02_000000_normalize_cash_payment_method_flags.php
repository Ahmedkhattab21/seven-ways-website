<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payment_methods')
            ->where(function ($query) {
                $query->where('type', 'cash')->orWhere('code', 'CASH');
            })
            ->where('is_cash', false)
            ->update(['is_cash' => true]);
    }

    public function down(): void
    {
        // Intentionally irreversible: the previous false values were invalid reference data.
    }
};
