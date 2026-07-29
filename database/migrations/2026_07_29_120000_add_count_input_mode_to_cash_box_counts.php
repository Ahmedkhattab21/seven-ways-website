<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_box_counts', function (Blueprint $table): void {
            $table->string('count_input_mode', 20)->nullable()->after('count_type');
        });
    }

    public function down(): void
    {
        Schema::table('cash_box_counts', function (Blueprint $table): void {
            $table->dropColumn('count_input_mode');
        });
    }
};
