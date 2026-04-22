<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('variant_id')->nullable();
            $table->uuid('warehouse_id')->nullable();
            $table->enum('transaction_type', [
                'PURCHASE',
                'SALE',
                'ADJUSTMENT',
                'TRANSFER_IN',
                'TRANSFER_OUT',
                'SALE_RETURN',
                'PURCHASE_RETURN',
            ])->nullable();
            $table->decimal('quantity', 14, 4)->nullable();
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->date('transaction_date')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('variant_id')->references('id')->on('product_variants');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('created_by')->references('id')->on('users');

            $table->index('variant_id');
            $table->index('warehouse_id');
            $table->index('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
