<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('sales')
            ->whereNull('status')
            ->update(['status' => 'POSTED']);

        DB::table('purchases')
            ->whereNull('status')
            ->update(['status' => 'POSTED']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: this is a data backfill migration.
    }
};
