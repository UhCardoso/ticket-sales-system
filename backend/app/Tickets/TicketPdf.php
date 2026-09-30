<?php

namespace App\Tickets;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class TicketPdf
{
    /**
     * Renders the order's valid tickets as a PDF, one page per ticket with its QR code.
     *
     * Rendered from the stored codes on demand, not saved to disk: the same order always
     * yields the same document, so a retried e-mail needs no file kept in sync.
     */
    public function render(Order $order): string
    {
        $order->loadMissing('ticketBatch.event');

        $qrCode = new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale' => 8,
        ]));

        $tickets = $order->tickets()->valid()->orderBy('id')->get()->map(fn ($ticket) => [
            'code' => $ticket->code,
            'qr_code' => $qrCode->render($ticket->code),
        ]);

        return Pdf::loadView('pdf.tickets', [
            'order' => $order,
            'tickets' => $tickets,
        ])->output();
    }
}
