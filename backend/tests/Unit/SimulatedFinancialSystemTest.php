<?php

namespace Tests\Unit;

use App\Exceptions\FinancialSystemUnavailableException;
use App\Financial\SimulatedFinancialSystem;
use App\Models\Order;
use Tests\TestCase;

class SimulatedFinancialSystemTest extends TestCase
{
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config(['financial.min_delay_ms' => 0, 'financial.max_delay_ms' => 0]);
        $this->order = (new Order)->forceFill(['id' => 42]);
    }

    public function test_registering_the_same_order_again_returns_the_original_reference(): void
    {
        config(['financial.failure_rate' => 0]);
        $financialSystem = new SimulatedFinancialSystem;

        $this->assertSame($financialSystem->register($this->order), $financialSystem->register($this->order));
    }

    public function test_a_failed_call_may_have_recorded_the_sale_but_never_records_it_twice(): void
    {
        $financialSystem = new SimulatedFinancialSystem;

        config(['financial.failure_rate' => 1]);

        foreach (range(1, 20) as $attempt) {
            try {
                $financialSystem->register($this->order);
                $this->fail('Com taxa de falha 1, toda chamada deveria falhar.');
            } catch (FinancialSystemUnavailableException) {
                //
            }
        }

        // Com 20 falhas, metade delas depois de registrar, a venda existe — e uma vez só.
        config(['financial.failure_rate' => 0]);
        $reference = $financialSystem->register($this->order);

        $this->assertSame($reference, $financialSystem->register($this->order));
    }
}
