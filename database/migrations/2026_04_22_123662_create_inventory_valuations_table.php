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
        Schema::create('inventory_valuation', function (Blueprint $table) {
            $table->uuid('variant_id');
            $table->uuid('warehouse_id');
            $table->decimal('total_quantity', 14, 4)->nullable();
            $table->decimal('total_cost', 14, 4)->nullable();
            $table->decimal('avg_unit_cost', 14, 4)->nullable();

            $table->primary(['variant_id', 'warehouse_id']);
            $table->foreign('variant_id')->references('id')->on('product_variants');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_valuation');
    }
};
