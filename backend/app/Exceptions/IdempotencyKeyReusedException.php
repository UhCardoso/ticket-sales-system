<?php

namespace App\Exceptions;

use DomainException;

class IdempotencyKeyReusedException extends DomainException
{
    public function __construct(string $idempotencyKey)
    {
        parent::__construct(
            "A Idempotency-Key {$idempotencyKey} já foi usada em uma compra com dados diferentes."
        );
    }
}
