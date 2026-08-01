<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('default_sales_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_sellable')->default(true);
            $table->decimal('minimum_stock', 16, 6)->nullable();
            $table->decimal('maximum_stock', 16, 6)->nullable();
            $table->decimal('reorder_quantity', 16, 6)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'product_id']);
            $table->index(['company_id', 'branch_id', 'is_available', 'is_sellable'], 'branch_products_catalog_index');
        });

        Schema::create('branch_product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('price', 19, 4);
            $table->decimal('minimum_price', 19, 4)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(
                ['company_id', 'branch_id', 'product_id', 'is_active', 'priority', 'effective_from'],
                'branch_product_price_resolution_index'
            );
        });

        Schema::create('promotion_products', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['promotion_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_products');
        Schema::dropIfExists('branch_product_prices');
        Schema::dropIfExists('branch_products');
    }
};
