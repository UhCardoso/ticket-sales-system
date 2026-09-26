<?php

namespace App\Exceptions;

use App\Models\TicketBatch;
use DomainException;

class BatchSoldOutException extends DomainException
{
    public function __construct(TicketBatch $batch, int $requested)
    {
        parent::__construct(
            "Quantidade indisponível no lote {$batch->name}: solicitado {$requested}, disponível {$batch->availableQuantity()}."
        );
    }
}
