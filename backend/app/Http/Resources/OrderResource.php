<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'ticket_batch_id' => $this->ticket_batch_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total' => $this->total,
            'buyer' => [
                'name' => $this->buyer_name,
                'email' => $this->buyer_email,
                'document' => $this->buyer_document,
            ],
            'payment' => [
                'reference' => $this->payment_reference,
                'checkout_url' => $this->payment_url,
            ],
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
