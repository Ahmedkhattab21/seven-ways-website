<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_product_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->foreignId('sales_invoice_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 16, 6);
            $table->text('reason');
            $table->foreignId('stock_movement_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sales_credit_note_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['sales_invoice_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_product_returns');
    }
};
