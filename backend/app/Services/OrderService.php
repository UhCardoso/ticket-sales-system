<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\BatchSoldOutException;
use App\Exceptions\IdempotencyKeyReusedException;
use App\Models\Order;
use App\Models\TicketBatch;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class OrderService
{
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
            return $existing;
        }

        try {
            return DB::transaction(function () use ($data, $idempotencyKey) {
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
            return $this->findByIdempotencyKey($idempotencyKey, $data);
        }
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
                ->decrement('reserved_quantity', $order->quantity);

            $order->transitionTo(OrderStatus::Expired);

            return true;
        });
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
