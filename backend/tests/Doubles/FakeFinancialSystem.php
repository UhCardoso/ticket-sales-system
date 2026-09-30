<?php

namespace Tests\Doubles;

use App\Contracts\FinancialSystemInterface;
use App\Exceptions\FinancialSystemUnavailableException;
use App\Models\Order;

/**
 * Deterministic financial system: instant, fails only when told to, and records every
 * call so tests can tell attempts apart from registered sales.
 */
class FakeFinancialSystem implements FinancialSystemInterface
{
    /** @var array<int, string> Registered sales: order id => reference. */
    public array $sales = [];

    public int $calls = 0;

    private int $failuresLeft = 0;

    /**
     * Makes the next $times calls fail as the real system does ~20% of the time.
     */
    public function failNext(int $times): self
    {
        $this->failuresLeft = $times;

        return $this;
    }

    /**
     * Registers the sale, idempotently per order id, unless a failure is queued.
     */
    public function register(Order $order): string
    {
        $this->calls++;

        if ($this->failuresLeft > 0) {
            $this->failuresLeft--;

            throw new FinancialSystemUnavailableException($order);
        }

        return $this->sales[$order->id] ??= "fin_test_{$order->id}";
    }
}
