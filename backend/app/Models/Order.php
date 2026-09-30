<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Order extends Model
{
    protected $fillable = [
        'ticket_batch_id',
        'idempotency_key',
        'status',
        'buyer_name',
        'buyer_email',
        'buyer_document',
        'quantity',
        'unit_price',
        'total',
        'payment_reference',
        'payment_url',
        'expires_at',
        'last_notification_at',
        'paid_at',
        'tickets_issued_at',
        'receipt_sent_at',
        'tickets_sent_at',
        'financial_registered_at',
        'financial_reference',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
            'expires_at' => 'datetime',
            'last_notification_at' => 'datetime',
            'paid_at' => 'datetime',
            'tickets_issued_at' => 'datetime',
            'receipt_sent_at' => 'datetime',
            'tickets_sent_at' => 'datetime',
            'financial_registered_at' => 'datetime',
        ];
    }

    public function ticketBatch(): BelongsTo
    {
        return $this->belongsTo(TicketBatch::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function gatewayNotifications(): HasMany
    {
        return $this->hasMany(GatewayNotification::class);
    }

    /**
     * Pending orders whose payment deadline has passed.
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', OrderStatus::Pending)->where('expires_at', '<=', now());
    }

    public function isOverdue(): bool
    {
        return $this->status === OrderStatus::Pending && ! $this->expires_at->isFuture();
    }

    /**
     * Whether a notification that happened at the given instant is older than the last
     * one already applied to this order, and must therefore be ignored.
     *
     * @param  $occurredAt  When the gateway says the notification happened
     */
    public function hasNewerNotificationThan(Carbon $occurredAt): bool
    {
        return $this->last_notification_at?->greaterThan($occurredAt) === true;
    }

    /**
     * Single entry point for status changes: validates the transition before saving.
     *
     * @throws InvalidOrderTransitionException
     */
    public function transitionTo(OrderStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw new InvalidOrderTransitionException($this, $next);
        }

        $this->update(['status' => $next]);
    }

    /**
     * Verifies that the order's data matches a purchase request, for idempotency checks.
     *
     * @param  array<string, mixed>  $data
     */
    public function matchesPurchase(array $data): bool
    {
        foreach ($data as $field => $value) {
            if ((string) $this->getAttribute($field) !== (string) $value) {
                return false;
            }
        }

        return true;
    }
}
