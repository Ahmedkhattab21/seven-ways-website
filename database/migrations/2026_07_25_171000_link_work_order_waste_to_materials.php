<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_waste_records', function (Blueprint $table) {
            $table->foreignId('work_order_material_id')->nullable()->after('work_order_service_id')
                ->constrained('work_order_materials')->restrictOnDelete();
            $table->unique('work_order_material_id', 'work_order_waste_material_unique');
        });
    }

    public function down(): void
    {
        Schema::table('work_order_waste_records', function (Blueprint $table) {
            $table->dropForeign(['work_order_material_id']);
            $table->dropUnique('work_order_waste_material_unique');
            $table->dropColumn('work_order_material_id');
        });
    }
};
