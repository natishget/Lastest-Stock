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
        Schema::table('sales', function (Blueprint $table): void {
            $table->index('sale_date');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->index('variant_id');
            $table->index('sale_id');
        });

        Schema::table('cogs_entries', function (Blueprint $table): void {
            $table->index('sale_item_id');
            $table->index('variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cogs_entries', function (Blueprint $table): void {
            $table->dropIndex(['sale_item_id']);
            $table->dropIndex(['variant_id']);
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropIndex(['variant_id']);
            $table->dropIndex(['sale_id']);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(['sale_date']);
        });
    }
};
