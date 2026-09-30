<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Mail\TicketsMail;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Mail;

class SendTicketsEmail implements ShouldQueue
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
     * Sends the issued tickets to the buyer as a PDF attachment, once.
     *
     * Runs chained after IssueTickets. It still checks tickets_issued_at, and skips an
     * order refunded in the meantime: its tickets are no longer valid.
     */
    public function handle(): void
    {
        $order = $this->order->refresh();

        if ($order->tickets_sent_at !== null
            || $order->tickets_issued_at === null
            || $order->status !== OrderStatus::Paid) {
            return;
        }

        Mail::to($order->buyer_email)->send(new TicketsMail($order));

        $order->update(['tickets_sent_at' => now()]);
    }
}
