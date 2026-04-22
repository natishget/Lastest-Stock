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
        Schema::create('inventory_cost_layers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('variant_id')->nullable();
            $table->uuid('warehouse_id')->nullable();
            $table->decimal('remaining_qty', 14, 4)->nullable();
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->uuid('source_transaction_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('variant_id')->references('id')->on('product_variants');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->index('variant_id');
            $table->index('warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_layers');
    }
};
