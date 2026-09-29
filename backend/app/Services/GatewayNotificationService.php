<?php

namespace App\Services;

use App\Enums\GatewayNotificationType;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\GatewayNotification;
use App\Models\Order;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GatewayNotificationService
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Persists a raw gateway notification, idempotently per external identifier.
     *
     * A redelivery — the gateway resending the same notice, including after deciding our
     * response took too long — returns the stored notification instead of inserting a
     * second one. The guarantee is the unique index, not a prior existence check, because
     * two simultaneous redeliveries would both pass the check.
     *
     * @param  $data  Validated notification payload
     */
    public function record(array $data): GatewayNotification
    {
        try {
            return GatewayNotification::create([
                'external_id' => $data['external_id'],
                'order_id' => $data['order_id'],
                'type' => $data['type'],
                'occurred_at' => $data['occurred_at'],
                'payload' => $data,
            ]);
        } catch (UniqueConstraintViolationException) {
            return GatewayNotification::firstWhere('external_id', $data['external_id']);
        }
    }

    /**
     * Applies the order's unresolved notifications in occurred_at order.
     *
     * Delivery order is not trusted. Every notification is replayed in the order the
     * gateway says the events happened, and the order's last_notification_at watermark
     * discards anything older than what was already applied. A notification whose
     * transition is not reachable yet is left unresolved, so that a refund delivered
     * before its approval still lands once the approval arrives.
     */
    public function apply(Order $order): void
    {
        foreach ($order->gatewayNotifications()->unresolved()->get() as $notification) {
            if ($order->hasNewerNotificationThan($notification->occurred_at)) {
                $notification->discard(GatewayNotification::DISCARD_STALE);

                continue;
            }

            try {
                $order = $this->settle($order, $notification);
            } catch (InvalidOrderTransitionException $e) {
                if ($order->status === OrderStatus::Pending) {
                    continue;
                }

                Log::warning('Aviso do gateway incoerente com a situação do pedido.', [
                    'gateway_notification_id' => $notification->id,
                    'order_id' => $order->id,
                    'reason' => $e->getMessage(),
                ]);

                $notification->discard(GatewayNotification::DISCARD_UNREACHABLE);
            }
        }
    }

    /**
     * Applies one notification, advancing the watermark in the same transaction.
     *
     * Marking the notification and moving the watermark must commit together with the
     * state change: a crash in between would let the notification be replayed against an
     * order that already moved, and be discarded as incoherent.
     *
     * @throws InvalidOrderTransitionException
     */
    private function settle(Order $order, GatewayNotification $notification): Order
    {
        return DB::transaction(function () use ($order, $notification) {
            $order = match ($notification->type) {
                GatewayNotificationType::Approved => $this->orders->markAsPaid($order),
                GatewayNotificationType::Rejected => $this->orders->reject($order),
                GatewayNotificationType::Refunded => $this->orders->refund($order),
            };

            $notification->markApplied();
            $order->update(['last_notification_at' => $notification->occurred_at]);

            return $order;
        });
    }
}
