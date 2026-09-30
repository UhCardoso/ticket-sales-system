<?php

namespace App\Contracts;

use App\Exceptions\FinancialSystemUnavailableException;
use App\Models\Order;

interface FinancialSystemInterface
{
    /**
     * Registers the order's sale in the client's financial system and returns its reference.
     *
     * Implementations must treat the order id as an idempotency key: registering the same
     * order again returns the original reference instead of recording a second sale. That
     * covers the case our marker cannot — the call succeeded but the job died before
     * saving the result.
     *
     * @throws FinancialSystemUnavailableException
     */
    public function register(Order $order): string;
}
