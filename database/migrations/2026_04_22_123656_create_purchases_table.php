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
        Schema::create('purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('supplier_name')->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->date('purchase_date')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
