<?php

namespace Tests;

use App\Contracts\FinancialSystemInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Doubles\FakeFinancialSystem;

abstract class TestCase extends BaseTestCase
{
    protected FakeFinancialSystem $financialSystem;

    /**
     * Swaps the slow, unstable simulated financial system for a deterministic one: with
     * the sync queue, every approved payment in a test would otherwise wait 2–5s on it.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->financialSystem = new FakeFinancialSystem;
        $this->app->instance(FinancialSystemInterface::class, $this->financialSystem);
    }
}
