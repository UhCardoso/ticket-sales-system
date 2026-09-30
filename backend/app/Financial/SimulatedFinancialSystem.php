<?php

namespace App\Financial;

use App\Contracts\FinancialSystemInterface;
use App\Exceptions\FinancialSystemUnavailableException;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SimulatedFinancialSystem implements FinancialSystemInterface
{
    /**
     * Simulates the client's financial system: slow on every call and failing a share of them.
     *
     * Half of the failures happen after the sale was recorded — the response is lost on
     * the way back — which is the case only the idempotency key protects against.
     * Registered sales are kept in the cache under the order id, which is how that key is
     * emulated: a repeated registration gets the original reference.
     */
    public function register(Order $order): string
    {
        usleep(random_int(config('financial.min_delay_ms'), config('financial.max_delay_ms')) * 1000);

        $fails = random_int(1, 100) <= config('financial.failure_rate') * 100;
        $losesResponse = random_int(0, 1) === 1;

        if ($fails && ! $losesResponse) {
            throw new FinancialSystemUnavailableException($order);
        }

        $reference = Cache::rememberForever(
            "financial-system:sale:{$order->id}",
            fn () => 'fin_'.Str::lower(Str::random(20)),
        );

        if ($fails) {
            throw new FinancialSystemUnavailableException($order);
        }

        return $reference;
    }
}
