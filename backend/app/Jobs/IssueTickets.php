<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueTickets implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [5, 15, 30];

    public function __construct(private readonly Order $order) {}

    /**
     * Issues one ticket per unit of the order.
     *
     * The queue is at-least-once, so the job checks tickets_issued_at before acting: a
     * retry must not issue a second set. It also refuses to issue for an order that is no
     * longer paid, which happens when a refund notification was applied before this ran.
     */
    public function handle(): void
    {
        DB::transaction(function () {
            $order = Order::lockForUpdate()->findOrFail($this->order->id);

            if ($order->tickets_issued_at !== null || $order->status !== OrderStatus::Paid) {
                return;
            }

            Ticket::insert(array_map(fn () => [
                'order_id' => $order->id,
                'code' => (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ], range(1, $order->quantity)));

            $order->update(['tickets_issued_at' => now()]);
        });
    }
}
