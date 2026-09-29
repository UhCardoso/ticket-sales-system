<?php

namespace App\Contracts;

use App\Models\Order;
use App\Payments\PaymentCharge;

interface PaymentGatewayInterface
{
    /**
     * Opens a charge for the order at the payment provider.
     *
     * Returns as soon as the charge exists: the payment result does not come back from
     * here, it arrives later as a gateway notification.
     */
    public function charge(Order $order): PaymentCharge;
}
