<?php

namespace App\Models;

use App\Enums\OrderEmail;
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
        'receipt_sending_at',
        'receipt_sent_at',
        'tickets_sending_at',
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
            'receipt_sending_at' => 'datetime',
            'receipt_sent_at' => 'datetime',
            'tickets_sending_at' => 'datetime',
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

    /**
     * Orders paid before the given instant with a post-payment effect still owed.
     *
     * The e-mails and the tickets only count while the order is paid; the financial
     * registration counts even after a refund, since the sale did happen. An e-mail
     * already claimed (sent or in doubt) is not owed.
     *
     * @param  $paidBefore  Orders paid after this are still within their normal retries
     */
    public function scopeWithPendingEffects(Builder $query, Carbon $paidBefore): void
    {
        $query->where('paid_at', '<=', $paidBefore)->where(fn (Builder $query) => $query
            ->whereNull('financial_registered_at')
            ->orWhere(fn (Builder $query) => $query
                ->where('status', OrderStatus::Paid)
                ->where(fn (Builder $query) => $query
                    ->whereNull('tickets_issued_at')
                    ->orWhereNull('receipt_sending_at')
                    ->orWhereNull('tickets_sending_at'))));
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
     * Claims the send of an e-mail, atomically: only one caller ever wins.
     *
     * The conditional UPDATE is the guarantee, not a prior read — two workers reading
     * "not sent" at the same time would both send.
     */
    public function claimEmail(OrderEmail $email): bool
    {
        $claimed = static::whereKey($this->id)
            ->whereNull($email->sendingColumn())
            ->whereNull($email->sentColumn())
            ->update([$email->sendingColumn() => now()]) === 1;

        $this->refresh();

        return $claimed;
    }

    /**
     * Gives the claim back after a failure that certainly did not deliver the message.
     */
    public function releaseEmailClaim(OrderEmail $email): void
    {
        $this->update([$email->sendingColumn() => null]);
    }

    /**
     * Records that the mail server accepted the message.
     */
    public function markEmailSent(OrderEmail $email): void
    {
        $this->update([$email->sentColumn() => now()]);
    }

    /**
     * Whether the e-mail was claimed but never confirmed: it may or may not have gone out.
     */
    public function isEmailInDoubt(OrderEmail $email): bool
    {
        return $this->getAttribute($email->sendingColumn()) !== null
            && $this->getAttribute($email->sentColumn()) === null;
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
