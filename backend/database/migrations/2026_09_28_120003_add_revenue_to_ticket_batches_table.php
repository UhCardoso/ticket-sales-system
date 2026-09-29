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
        Schema::table('ticket_batches', function (Blueprint $table) {
            $table->decimal('revenue', 10, 2)->default(0)->after('sold_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_batches', function (Blueprint $table) {
            $table->dropColumn('revenue');
        });
    }
};
