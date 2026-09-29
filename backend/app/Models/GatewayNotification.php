<?php

namespace App\Models;

use App\Enums\GatewayNotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatewayNotification extends Model
{
    public const DISCARD_STALE = 'older_than_last_applied';

    public const DISCARD_UNREACHABLE = 'order_status_does_not_allow';

    protected $fillable = [
        'external_id',
        'order_id',
        'type',
        'occurred_at',
        'payload',
        'applied_at',
        'discard_reason',
    ];

    /**
     * Attribute casts for the notification.
     */
    protected function casts(): array
    {
        return [
            'type' => GatewayNotificationType::class,
            'occurred_at' => 'datetime',
            'applied_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /**
     * Order this notification refers to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Limits to notifications neither applied nor discarded, in `occurred_at` order.
     *
     * Ordering by occurrence, not arrival, is what makes out-of-order delivery safe.
     */
    public function scopeUnresolved(Builder $query): void
    {
        $query->whereNull('applied_at')
            ->whereNull('discard_reason')
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    /**
     * Whether the notification is still pending application.
     */
    public function isUnresolved(): bool
    {
        return $this->applied_at === null && $this->discard_reason === null;
    }

    /**
     * Marks the notification as applied to its order.
     */
    public function markApplied(): void
    {
        $this->update(['applied_at' => now()]);
    }

    /**
     * Records that the notification was received but deliberately not applied.
     *
     * @param  $reason  One of the DISCARD_* constants
     */
    public function discard(string $reason): void
    {
        $this->update(['discard_reason' => $reason]);
    }
}
