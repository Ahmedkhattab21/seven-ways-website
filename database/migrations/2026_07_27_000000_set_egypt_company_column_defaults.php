<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE companies ALTER COLUMN country_code SET DEFAULT 'EG'");
        DB::statement("ALTER TABLE companies ALTER COLUMN currency_code SET DEFAULT 'EGP'");
        DB::statement("ALTER TABLE companies ALTER COLUMN timezone SET DEFAULT 'Africa/Cairo'");
    }

    public function down(): void
    {
        // Intentionally left unchanged: restoring Saudi defaults would be an unsafe
        // localization rollback. Apply a reviewed forward migration if defaults change.
    }
};
