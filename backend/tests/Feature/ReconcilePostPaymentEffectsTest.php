<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Mail\PaymentReceiptMail;
use App\Mail\TicketsMail;
use App\Models\Order;
use App\Models\TicketBatch;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReconcilePostPaymentEffectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * A paid order whose effect jobs never ran — lost by the queue, or never dispatched.
     *
     * @param  $state  Markers of the effects that did happen
     */
    private function paidOrderWithLostEffects(int $paidMinutesAgo = 61, array $state = []): Order
    {
        $order = app(OrderService::class)->create([
            'ticket_batch_id' => TicketBatch::factory()->create(['total_quantity' => 10])->id,
            'quantity' => 3,
            'buyer_name' => 'Maria Silva',
            'buyer_email' => 'maria@example.com',
            'buyer_document' => '12345678909',
        ], (string) Str::uuid());

        $order->forceFill([
            'status' => OrderStatus::Paid,
            'paid_at' => now()->subMinutes($paidMinutesAgo),
            ...$state,
        ])->save();

        return $order;
    }

    public function test_lost_effects_are_dispatched_again_and_happen_once(): void
    {
        $order = $this->paidOrderWithLostEffects();

        $this->artisan('orders:reconcile')->assertSuccessful();
        $this->artisan('orders:reconcile')->assertSuccessful();

        $order->refresh();
        $this->assertCount(3, $order->tickets);
        Mail::assertSent(PaymentReceiptMail::class, 1);
        Mail::assertSent(TicketsMail::class, 1);
        $this->assertSame(1, $this->financialSystem->calls);
        $this->assertNotNull($order->financial_registered_at);
    }

    public function test_only_the_missing_effect_is_dispatched_again(): void
    {
        // O financeiro esgotou as tentativas; o resto já tinha acontecido.
        $order = $this->paidOrderWithLostEffects(state: [
            'tickets_issued_at' => now(),
            'receipt_sending_at' => now(),
            'receipt_sent_at' => now(),
            'tickets_sending_at' => now(),
            'tickets_sent_at' => now(),
        ]);

        $this->artisan('orders:reconcile')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertCount(0, $order->tickets()->get());
        $this->assertSame(1, $this->financialSystem->calls);
    }

    public function test_orders_still_within_their_normal_retries_are_left_alone(): void
    {
        $order = $this->paidOrderWithLostEffects(paidMinutesAgo: 5);

        $this->artisan('orders:reconcile')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(0, $this->financialSystem->calls);
        $this->assertNull($order->refresh()->tickets_issued_at);
    }

    public function test_an_e_mail_in_doubt_is_reported_and_not_resent(): void
    {
        $order = $this->paidOrderWithLostEffects(state: [
            'tickets_issued_at' => now(),
            'tickets_sending_at' => now(),
            'tickets_sent_at' => now(),
            'financial_registered_at' => now(),
            'receipt_sending_at' => now(),
        ]);

        $this->artisan('orders:reconcile')
            ->expectsOutputToContain("E-mail receipt em dúvida nos pedidos: {$order->id}")
            ->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_a_refunded_order_is_still_registered_but_gets_no_e_mail(): void
    {
        $order = $this->paidOrderWithLostEffects();
        $order->forceFill(['status' => OrderStatus::Refunded])->save();

        $this->artisan('orders:reconcile')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertCount(0, $order->tickets()->get());
        $this->assertSame(1, $this->financialSystem->calls);
    }
}
