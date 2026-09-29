<?php

namespace App\Http\Resources;

use App\Models\GatewayNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GatewayNotification */
class GatewayNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'order_id' => $this->order_id,
            'type' => $this->type->value,
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'received_at' => $this->created_at->toIso8601String(),
            'applied_at' => $this->applied_at?->toIso8601String(),
            'discard_reason' => $this->discard_reason,
        ];
    }
}
