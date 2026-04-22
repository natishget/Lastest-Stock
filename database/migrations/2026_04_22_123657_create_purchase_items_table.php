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
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('purchase_id')->nullable();
            $table->uuid('variant_id')->nullable();
            $table->decimal('quantity', 14, 4)->nullable();
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->decimal('total_cost', 14, 2)->nullable();

            $table->foreign('purchase_id')->references('id')->on('purchases');
            $table->foreign('variant_id')->references('id')->on('product_variants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
