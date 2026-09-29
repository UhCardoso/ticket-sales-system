<?php

namespace App\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use Illuminate\Support\Str;

class FakePaymentGateway implements PaymentGatewayInterface
{
    /**
     * Issues a charge reference and a stand-in checkout URL.
     *
     * No payment happens here and none is simulated: the approved, rejected or refunded
     * outcome is delivered by the gateway:notify command to the notifications endpoint.
     */
    public function charge(Order $order): PaymentCharge
    {
        $reference = 'pay_'.Str::lower(Str::random(24));

        return new PaymentCharge(
            $reference,
            rtrim((string) config('app.url'), '/')."/fake-checkout/{$reference}",
        );
    }
}
