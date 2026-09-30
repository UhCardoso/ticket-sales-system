<?php

namespace App\Jobs;

use App\Enums\OrderEmail;
use App\Enums\OrderStatus;
use App\Mail\TicketsMail;
use App\Models\Order;
use Illuminate\Mail\Mailable;

class SendTicketsEmail extends SendOrderEmail
{
    /**
     * The tickets e-mail.
     */
    protected function email(): OrderEmail
    {
        return OrderEmail::Tickets;
    }

    /**
     * Only once the tickets exist, and only while the order is paid: a refund
     * invalidates them.
     */
    protected function shouldSend(Order $order): bool
    {
        return $order->tickets_issued_at !== null && $order->status === OrderStatus::Paid;
    }

    /**
     * Tickets e-mail with the PDF attached.
     */
    protected function mailable(Order $order): Mailable
    {
        return new TicketsMail($order);
    }
}
