<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Mail\PaymentReceiptMail;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Mail;

class SendReceiptEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60];

    public function __construct(private readonly Order $order) {}

    /**
     * One run at a time per order, so two deliveries of this job cannot both pass the
     * marker check and send twice.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->order->id))->releaseAfter(10)->expireAfter(120)];
    }

    /**
     * Sends the payment receipt to the buyer, once.
     *
     * The marker is written right after the send succeeds: a failure before that retries
     * the send, a retry after that finds the marker and does nothing.
     */
    public function handle(): void
    {
        $order = $this->order->refresh();

        if ($order->receipt_sent_at !== null || $order->status !== OrderStatus::Paid) {
            return;
        }

        Mail::to($order->buyer_email)->send(new PaymentReceiptMail($order));

        $order->update(['receipt_sent_at' => now()]);
    }
}
