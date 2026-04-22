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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->nullable();
            $table->string('color', 50)->nullable();
            $table->enum('origin', ['LOCAL', 'IMPORTED'])->nullable();
            $table->string('sku', 100)->unique()->nullable();
            $table->decimal('thickness', 10, 2)->nullable();
            $table->string('size', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('product_id')->references('id')->on('products');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
