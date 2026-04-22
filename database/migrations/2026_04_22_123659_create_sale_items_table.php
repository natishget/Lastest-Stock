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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sale_id')->nullable();
            $table->uuid('variant_id')->nullable();
            $table->decimal('quantity', 14, 4)->nullable();
            $table->decimal('selling_price', 14, 4)->nullable();
            $table->decimal('total_price', 14, 2)->nullable();

            $table->foreign('sale_id')->references('id')->on('sales');
            $table->foreign('variant_id')->references('id')->on('product_variants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
