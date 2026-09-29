<?php

namespace App\Payments;

readonly class PaymentCharge
{
    public function __construct(
        public string $reference,
        public string $checkoutUrl,
    ) {}
}
