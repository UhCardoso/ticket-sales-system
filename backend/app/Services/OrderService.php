<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\OrderEmail;
use App\Enums\OrderStatus;
use App\Exceptions\BatchSoldOutException;
use App\Exceptions\IdempotencyKeyReusedException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Jobs\IssueTickets;
use App\Jobs\RegisterInFinancialSystem;
use App\Jobs\SendReceiptEmail;
use App\Jobs\SendTicketsEmail;
use App\Models\Order;
use App\Models\TicketBatch;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(private readonly PaymentGatewayInterface $gateway) {}

    /**
     * Creates a pending order and reserves stock. Idempotent per key.
     *
     * @throws BatchSoldOutException
     * @throws IdempotencyKeyReusedException
     */
    public function create(array $data, string $idempotencyKey): Order
    {
        $existing = $this->findByIdempotencyKey($idempotencyKey, $data);

        if ($existing) {
            return $this->charge($existing);
        }

        try {
            $order = DB::transaction(function () use ($data, $idempotencyKey) {
                $batch = TicketBatch::lockForUpdate()->findOrFail($data['ticket_batch_id']);

                if ($batch->availableQuantity() < $data['quantity']) {
                    throw new BatchSoldOutException($batch, $data['quantity']);
                }

                $batch->increment('reserved_quantity', $data['quantity']);

                return Order::create([
                    'ticket_batch_id' => $batch->id,
                    'idempotency_key' => $idempotencyKey,
                    'status' => OrderStatus::Pending,
                    'buyer_name' => $data['buyer_name'],
                    'buyer_email' => $data['buyer_email'],
                    'buyer_document' => $data['buyer_document'],
                    'quantity' => $data['quantity'],
                    'unit_price' => $batch->price,
                    'total' => bcmul($batch->price, (string) $data['quantity'], 2),
                    'expires_at' => now()->addMinutes(config('orders.reservation_ttl')),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $order = $this->findByIdempotencyKey($idempotencyKey, $data);
        }

        return $this->charge($order);
    }

    /**
     * Confirms payment: the reservation becomes a sale and the post-payment effects run.
     *
     * The status change comes first so that an illegal transition fails before any
     * counter moves. The effects are dispatched after the commit, never inside it — the
     * worker would otherwise read a state that has not been committed yet.
     *
     * @throws InvalidOrderTransitionException
     */
    public function markAsPaid(Order $order): Order
    {
        $order = DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $order->transitionTo(OrderStatus::Paid);
            $order->update(['paid_at' => now()]);

            TicketBatch::lockForUpdate()
                ->findOrFail($order->ticket_batch_id)
                ->confirmSale($order->quantity, $order->total);

            return $order;
        });

        $this->dispatchPendingEffects($order);

        return $order;
    }

    /**
     * Dispatches a job for each post-payment effect whose marker is still empty.
     *
     * Used right after the payment and again by the reconciliation, so the markers — not
     * the queue — are the record of what is still owed: a job lost by the queue, or one
     * that exhausted its attempts, is dispatched again. Every job is idempotent, so
     * dispatching one that is still queued is harmless. An e-mail in doubt is left out:
     * resending it is exactly what must not happen.
     *
     * One job per effect, so a retry of one (the financial system fails ~20% of calls)
     * never repeats another. The tickets e-mail is chained after the issuance because it
     * needs the tickets to exist; the other effects are independent and run in parallel.
     */
    public function dispatchPendingEffects(Order $order): void
    {
        if ($order->tickets_issued_at === null) {
            Bus::chain([
                (new IssueTickets($order))->afterCommit(),
                new SendTicketsEmail($order),
            ])->dispatch();
        } elseif ($this->isEmailPending($order, OrderEmail::Tickets)) {
            SendTicketsEmail::dispatch($order)->afterCommit();
        }

        if ($this->isEmailPending($order, OrderEmail::Receipt)) {
            SendReceiptEmail::dispatch($order)->afterCommit();
        }

        if ($order->financial_registered_at === null) {
            RegisterInFinancialSystem::dispatch($order)->afterCommit();
        }
    }

    /**
     * Whether the e-mail was never claimed: neither sent nor in doubt.
     */
    private function isEmailPending(Order $order, OrderEmail $email): bool
    {
        return $order->getAttribute($email->sendingColumn()) === null;
    }

    /**
     * Rejects payment and releases the reserved tickets back to the batch.
     *
     * @throws InvalidOrderTransitionException
     */
    public function reject(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $order->transitionTo(OrderStatus::Rejected);

            TicketBatch::lockForUpdate()
                ->findOrFail($order->ticket_batch_id)
                ->releaseReservation($order->quantity);

            return $order;
        });
    }

    /**
     * Refunds a paid order: the sold tickets go back to the batch and the issued tickets
     * are invalidated, in the same transaction as the status change.
     *
     * @throws InvalidOrderTransitionException
     */
    public function refund(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $order->transitionTo(OrderStatus::Refunded);

            TicketBatch::lockForUpdate()
                ->findOrFail($order->ticket_batch_id)
                ->returnSale($order->quantity, $order->total);

            $order->tickets()->valid()->update(['invalidated_at' => now()]);

            return $order;
        });
    }

    /**
     * Expires an overdue pending order and releases its reserved tickets back to the batch.
     *
     * Returns false when the order is no longer expirable (e.g. paid between the
     * scheduler query and this call), which is an expected race, not an error.
     */
    public function expire(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (! $order->isOverdue()) {
                return false;
            }

            TicketBatch::lockForUpdate()
                ->findOrFail($order->ticket_batch_id)
                ->releaseReservation($order->quantity);

            $order->transitionTo(OrderStatus::Expired);

            return true;
        });
    }

    /**
     * Opens the payment charge for the order, once. The stored reference is the marker:
     * a retried purchase reuses the charge instead of opening a second one.
     */
    private function charge(Order $order): Order
    {
        if ($order->payment_reference !== null) {
            return $order;
        }

        $charge = $this->gateway->charge($order);

        $order->update([
            'payment_reference' => $charge->reference,
            'payment_url' => $charge->checkoutUrl,
        ]);

        return $order;
    }

    /**
     * Finds the order for an Idempotency-Key, ensuring it matches the same purchase.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws IdempotencyKeyReusedException
     */
    private function findByIdempotencyKey(string $idempotencyKey, array $data): ?Order
    {
        $order = Order::firstWhere('idempotency_key', $idempotencyKey);

        if ($order && ! $order->matchesPurchase($data)) {
            throw new IdempotencyKeyReusedException($idempotencyKey);
        }

        return $order;
    }
}
