<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventSalesSummaryResource extends JsonResource
{
    /**
     * The sales summary of one event: its own totals plus a breakdown per batch.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'date_time' => $this->date_time->toIso8601String(),
            'totals' => [
                'total_quantity' => $this->totalQuantity(),
                'sold_quantity' => $this->soldQuantity(),
                'reserved_quantity' => $this->reservedQuantity(),
                'available_quantity' => $this->availableQuantity(),
                'revenue' => $this->revenue(),
            ],
            'batches' => BatchSalesSummaryResource::collection($this->batches),
        ];
    }
}
