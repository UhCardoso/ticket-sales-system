<?php

namespace App\Exceptions;

use App\Models\Order;
use RuntimeException;

class FinancialSystemUnavailableException extends RuntimeException
{
    public function __construct(Order $order)
    {
        parent::__construct("O sistema financeiro não registrou a venda do pedido {$order->id}.");
    }
}
