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
        Schema::create('gateway_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->foreignId('order_id')->constrained();
            $table->string('type', 20);
            $table->timestamp('occurred_at');
            $table->json('payload');
            $table->timestamp('applied_at')->nullable();
            $table->string('discard_reason')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gateway_notifications');
    }
};
