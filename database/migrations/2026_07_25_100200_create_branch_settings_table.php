<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('invoice_prefix', 20)->nullable();
            $table->string('quotation_prefix', 20)->nullable();
            $table->string('work_order_prefix', 20)->nullable();
            $table->string('warranty_prefix', 20)->nullable();
            $table->decimal('maximum_discount_percentage', 5, 2)->default(0);
            $table->boolean('requires_discount_approval')->default(true);
            $table->boolean('requires_invoice_cancel_approval')->default(true);
            $table->boolean('allow_negative_stock')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_settings');
    }
};
