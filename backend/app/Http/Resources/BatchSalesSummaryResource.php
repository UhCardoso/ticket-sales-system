<?php

namespace App\Http\Resources;

use App\Models\TicketBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketBatch */
class BatchSalesSummaryResource extends JsonResource
{
    /**
     * The four numbers requirement 6.1 asks for, per batch.
     *
     * Unlike TicketBatchResource — the purchase path's contract, which deliberately hides
     * the counters — the dashboard exists to show them. Money stays a decimal string: this
     * is a contract for the panel and the integration team, so the consumer formats it.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'total_quantity' => $this->total_quantity,
            'sold_quantity' => $this->sold_quantity,
            'reserved_quantity' => $this->reserved_quantity,
            'available_quantity' => $this->availableQuantity(),
            'revenue' => $this->revenue,
        ];
    }
}
