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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_batch_id')->constrained();
            $table->uuid('idempotency_key')->unique();
            $table->string('status', 20);
            $table->string('buyer_name');
            $table->string('buyer_email');
            $table->string('buyer_document', 11);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 8, 2);
            $table->decimal('total', 10, 2);
            $table->timestamp('expires_at');
            $table->timestamps();

            // Busca do scheduler por reservas pendentes vencidas.
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
