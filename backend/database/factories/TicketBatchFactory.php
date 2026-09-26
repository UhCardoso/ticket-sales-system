<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketBatch>
 */
class TicketBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => '1º Lote',
            'price' => '50.00',
            'total_quantity' => 100,
        ];
    }
}
