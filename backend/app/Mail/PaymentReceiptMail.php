<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PaymentReceiptMail extends Mailable
{
    /**
     * Not ShouldQueue on purpose: it is sent from inside SendReceiptEmail, which is the
     * unit of retry and owns the receipt_sent_at marker.
     */
    public function __construct(public readonly Order $order) {}

    /**
     * Subject line of the receipt.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: "Comprovante de pagamento — pedido {$this->order->id}");
    }

    /**
     * Markdown view of the receipt.
     */
    public function content(): Content
    {
        $this->order->loadMissing('ticketBatch.event');

        return new Content(markdown: 'mail.payment-receipt');
    }
}
