<?php

namespace App\Http\Resources;

use App\Models\TicketBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketBatch */
class TicketBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'available_quantity' => $this->availableQuantity(),
            'event' => [
                'id' => $this->event->id,
                'name' => $this->event->name,
                'date_time' => $this->event->date_time->toIso8601String(),
            ],
        ];
    }
}
