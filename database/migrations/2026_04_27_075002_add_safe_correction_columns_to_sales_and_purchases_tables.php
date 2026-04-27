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
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('status', ['POSTED', 'VOIDED'])->default('POSTED')->after('sale_date');
            $table->string('reference_type', 20)->nullable()->after('status');
            $table->uuid('reference_id')->nullable()->after('reference_type');
            $table->text('notes')->nullable()->after('reference_id');

            $table->index('status');
            $table->index('reference_id');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->enum('status', ['POSTED', 'VOIDED'])->default('POSTED')->after('purchase_date');
            $table->string('reference_type', 20)->nullable()->after('status');
            $table->uuid('reference_id')->nullable()->after('reference_type');
            $table->text('notes')->nullable()->after('reference_id');

            $table->index('status');
            $table->index('reference_id');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->index('transaction_type');
            $table->index('reference_id');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['reference_id']);

            $table->dropColumn(['status', 'reference_type', 'reference_id', 'notes']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['reference_id']);

            $table->dropColumn(['status', 'reference_type', 'reference_id', 'notes']);
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex(['transaction_type']);
            $table->dropIndex(['reference_id']);
            $table->dropIndex(['reference_type', 'reference_id']);
        });
    }
};
