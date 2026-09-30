<?php

namespace App\Exceptions;

use App\Models\Order;
use RuntimeException;

class FinancialSystemUnavailableException extends RuntimeException
{
    /**
     * The call failed without a confirmation — the sale may or may not have been recorded.
     */
    public function __construct(Order $order)
    {
        parent::__construct("O sistema financeiro não confirmou o registro da venda do pedido {$order->id}.");
    }
}
