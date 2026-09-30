<?php

namespace App\Mail;

use App\Models\Order;
use App\Tickets\TicketPdf;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TicketsMail extends Mailable
{
    /**
     * Not ShouldQueue on purpose: it is sent from inside SendTicketsEmail, which is the
     * unit of retry and owns the tickets_sent_at marker.
     */
    public function __construct(public readonly Order $order) {}

    /**
     * Subject line of the tickets e-mail.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: "Seus ingressos — pedido {$this->order->id}");
    }

    /**
     * Markdown view of the tickets e-mail.
     */
    public function content(): Content
    {
        $this->order->loadMissing('ticketBatch.event');

        return new Content(markdown: 'mail.tickets');
    }

    /**
     * The tickets PDF, rendered when the message is built.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => app(TicketPdf::class)->render($this->order), "ingressos-pedido-{$this->order->id}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
