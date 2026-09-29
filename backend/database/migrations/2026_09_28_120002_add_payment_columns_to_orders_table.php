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
            $table->string('payment_reference')->nullable()->after('total');
            $table->string('payment_url')->nullable()->after('payment_reference');

            $table->timestamp('last_notification_at')->nullable()->after('expires_at');
            $table->timestamp('tickets_issued_at')->nullable()->after('last_notification_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_reference',
                'payment_url',
                'last_notification_at',
                'tickets_issued_at',
            ]);
        });
    }
};
