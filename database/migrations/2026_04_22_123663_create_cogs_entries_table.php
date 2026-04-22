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
        Schema::create('cogs_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sale_item_id')->nullable();
            $table->uuid('variant_id')->nullable();
            $table->decimal('quantity', 14, 4)->nullable();
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->decimal('total_cost', 14, 2)->nullable();
            $table->enum('costing_method', ['FIFO', 'LIFO', 'WEIGHTED_AVERAGE'])->nullable();
            $table->uuid('source_layer_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('sale_item_id')->references('id')->on('sale_items');
            $table->foreign('variant_id')->references('id')->on('product_variants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cogs_entries');
    }
};
