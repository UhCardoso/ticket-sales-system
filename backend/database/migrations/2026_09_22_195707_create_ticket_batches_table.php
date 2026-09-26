<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->decimal('price', 8, 2);
            $table->unsignedInteger('total_quantity');
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('sold_quantity')->default(0);
            $table->timestamps();
        });

        // Rede de segurança do invariante de estoque: vale mesmo com bug na aplicação.
        DB::statement('ALTER TABLE ticket_batches ADD CONSTRAINT ticket_batches_stock_check CHECK (reserved_quantity + sold_quantity <= total_quantity)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_batches');
    }
};
