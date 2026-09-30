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
     * Registered sales are kept in the cache under the order id, which is how the real
     * system's idempotency key is emulated: a repeated registration gets the same reference.
     */
    public function register(Order $order): string
    {
        usleep(random_int(config('financial.min_delay_ms'), config('financial.max_delay_ms')) * 1000);

        if (random_int(1, 100) <= config('financial.failure_rate') * 100) {
            throw new FinancialSystemUnavailableException($order);
        }

        return Cache::rememberForever(
            "financial-system:sale:{$order->id}",
            fn () => 'fin_'.Str::lower(Str::random(20)),
        );
    }
}
