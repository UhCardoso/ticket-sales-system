<?php

namespace App\Enums;

enum OrderEmail: string
{
    case Receipt = 'receipt';
    case Tickets = 'tickets';

    /**
     * Column set when a job claims the send, before talking to the mail server.
     */
    public function sendingColumn(): string
    {
        return "{$this->value}_sending_at";
    }

    /**
     * Column set once the mail server has accepted the message.
     */
    public function sentColumn(): string
    {
        return "{$this->value}_sent_at";
    }
}
