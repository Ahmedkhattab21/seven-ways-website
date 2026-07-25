<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('is_active');
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('transfer_number');
            $table->string('transfer_type', 20);
            $table->foreignId('from_branch_id')->constrained('branches');
            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_branch_id')->constrained('branches');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->foreignId('transit_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expected_delivery_at')->nullable();
            $table->string('shipping_reference')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('stock_transfers')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'transfer_number']);
            $table->index(['company_id', 'status', 'requested_at']);
            $table->index(['from_branch_id', 'to_branch_id', 'status']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('item_type', 20);
            $table->foreignId('roll_id')->nullable()->constrained('inventory_rolls')->nullOnDelete();
            $table->foreignId('scrap_id')->nullable()->constrained('roll_scraps')->nullOnDelete();
            $table->decimal('requested_quantity', 19, 6)->nullable();
            $table->decimal('approved_quantity', 19, 6)->nullable();
            $table->decimal('prepared_quantity', 19, 6)->nullable();
            $table->decimal('shipped_quantity', 19, 6)->nullable();
            $table->decimal('received_quantity', 19, 6)->default(0);
            $table->decimal('rejected_quantity', 19, 6)->default(0);
            $table->decimal('damaged_quantity', 19, 6)->default(0);
            $table->decimal('shortage_quantity', 19, 6)->default(0);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('unit_cost', 19, 4)->default(0);
            $table->decimal('total_cost', 19, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['stock_transfer_id', 'item_type']);
            $table->unique(['stock_transfer_id', 'roll_id']);
            $table->unique(['stock_transfer_id', 'scrap_id']);
        });

        Schema::create('stock_transfer_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_transfer_item_id')->constrained()->cascadeOnDelete();
            $table->string('discrepancy_type', 20);
            $table->decimal('quantity', 19, 6)->nullable();
            $table->text('description');
            $table->foreignId('reported_by')->constrained('users');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();
            $table->index(['stock_transfer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_discrepancies');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::table('warehouses', fn (Blueprint $table) => $table->dropColumn('is_system'));
    }
};
