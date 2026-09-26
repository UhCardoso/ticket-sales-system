<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\TicketBatch;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $event1 = Event::create([
            'name' => 'Show de Rock',
            'date_time' => now()->addDays(30),
        ]);

        $this->createBatch($event1, '1º Lote', '50.00', 30);
        $this->createBatch($event1, '2º Lote', '70.00', 50);

        $event2 = Event::create([
            'name' => 'Festival de Verão',
            'date_time' => now()->addDays(60),
        ]);

        $this->createBatch($event2, '1º Lote', '80.00', 27);
        $this->createBatch($event2, '2º Lote', '100.00', 53);
    }

    private function createBatch(Event $event, string $name, string $price, int $totalQuantity): TicketBatch
    {
        return $event->batches()->create([
            'name' => $name,
            'price' => $price,
            'total_quantity' => $totalQuantity,
        ]);
    }
}
