<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use App\Models\Order;
use DomainException;

class InvalidOrderTransitionException extends DomainException
{
    public function __construct(Order $order, OrderStatus $next)
    {
        parent::__construct(
            "O pedido {$order->id} não pode passar de {$order->status->value} para {$next->value}."
        );
    }
}
