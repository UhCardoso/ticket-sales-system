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
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('last_notification_at');

            $table->timestamp('receipt_sent_at')->nullable()->after('tickets_issued_at');
            $table->timestamp('tickets_sent_at')->nullable()->after('receipt_sent_at');
            $table->timestamp('financial_registered_at')->nullable()->after('tickets_sent_at');
            $table->string('financial_reference')->nullable()->after('financial_registered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'paid_at',
                'receipt_sent_at',
                'tickets_sent_at',
                'financial_registered_at',
                'financial_reference',
            ]);
        });
    }
};
