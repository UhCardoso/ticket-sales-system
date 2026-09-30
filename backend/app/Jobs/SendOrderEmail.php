<?php

namespace App\Jobs;

use App\Enums\OrderEmail;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Sends one of the order's e-mails at most once, and never silently drops it.
 *
 * Exactly-once delivery over SMTP is impossible: the server may accept the message and
 * the worker die before recording it. So the send is claimed first, and a claim with no
 * confirmation is "in doubt" — never resent automatically, only flagged for a person.
 */
abstract class SendOrderEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60];

    public function __construct(protected readonly Order $order) {}

    /**
     * Which of the order's e-mails this job sends.
     */
    abstract protected function email(): OrderEmail;

    /**
     * Whether the order is still in a state where this e-mail makes sense.
     */
    abstract protected function shouldSend(Order $order): bool;

    /**
     * The message to send, built for the given order.
     */
    abstract protected function mailable(Order $order): Mailable;

    /**
     * Serializes runs per order, so finding a claim always means a previous run died
     * mid-send — never that another run is still sending.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->order->id))->releaseAfter(10)->expireAfter(120)];
    }

    /**
     * Claims the send, sends, and confirms; an unconfirmed claim is left in doubt.
     */
    public function handle(): void
    {
        $order = $this->order->refresh();
        $email = $this->email();

        if ($order->getAttribute($email->sentColumn()) !== null || ! $this->shouldSend($order)) {
            return;
        }

        if (! $order->claimEmail($email)) {
            $this->reportInDoubt($order);

            return;
        }

        try {
            Mail::to($order->buyer_email)->send($this->mailable($order));
        } catch (Throwable $e) {
            if ($this->mayHaveBeenDelivered($e)) {
                $this->reportInDoubt($order);

                return;
            }

            $order->releaseEmailClaim($email);

            throw $e;
        }

        $order->markEmailSent($email);
    }

    /**
     * Whether the failure happened after the message body started going to the server.
     *
     * Before the SMTP DATA command the server holds nothing it could deliver, so the
     * failure is certain and the send can be retried. From DATA on, the server may have
     * accepted the message even though we saw an error.
     */
    private function mayHaveBeenDelivered(Throwable $e): bool
    {
        return $e instanceof TransportExceptionInterface
            && str_contains($e->getDebug(), '> DATA');
    }

    /**
     * Flags an unconfirmed send so a person can check it, instead of resending blindly.
     */
    private function reportInDoubt(Order $order): void
    {
        Log::warning('E-mail em dúvida: pode ter sido enviado, não será reenviado automaticamente.', [
            'order_id' => $order->id,
            'email' => $this->email()->value,
        ]);
    }
}
