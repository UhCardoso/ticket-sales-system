<?php

namespace Tests\Doubles;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Mail transport that fails the way SMTP does: each queued failure carries the SMTP
 * transcript up to the point it broke, as Symfony's SmtpTransport reports it.
 */
class FlakyMailTransport extends AbstractTransport
{
    public const BEFORE_DATA = "[2026-09-29T12:00:00.000000] > MAIL FROM:<shop@example.com>\n";

    public const AFTER_DATA = self::BEFORE_DATA."[2026-09-29T12:00:00.000000] > DATA\n";

    /** Every attempt that reached the transport, failed or not. */
    public int $attempts = 0;

    /** @var list<string> Transcripts of the failures still to happen, in order. */
    private array $failures = [];

    /**
     * Makes the next attempt fail with the given SMTP transcript.
     */
    public function failNextWith(string $transcript): self
    {
        $this->failures[] = $transcript;

        return $this;
    }

    /**
     * Fails with the next queued transcript, or accepts the message.
     */
    protected function doSend(SentMessage $message): void
    {
        $this->attempts++;

        if ($transcript = array_shift($this->failures)) {
            $e = new TransportException('Connection to "mailpit:1025" timed out.');
            $e->appendDebug($transcript);

            throw $e;
        }
    }

    public function __toString(): string
    {
        return 'flaky://';
    }
}
