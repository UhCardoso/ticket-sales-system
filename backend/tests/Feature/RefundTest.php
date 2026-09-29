<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\IssueTickets;
use App\Models\GatewayNotification;
use App\Models\Order;
use App\Models\TicketBatch;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    private function placeOrder(TicketBatch $batch, int $quantity = 3): Order
    {
        return app(OrderService::class)->create([
            'ticket_batch_id' => $batch->id,
            'quantity' => $quantity,
            'buyer_name' => 'Maria Silva',
            'buyer_email' => 'maria@example.com',
            'buyer_document' => '12345678909',
        ], (string) Str::uuid());
    }

    private function notify(Order $order, string $type, ?string $externalId = null)
    {
        return $this->postJson('/api/gateway/notifications', [
            'external_id' => $externalId ?? 'evt_'.Str::lower(Str::random(20)),
            'order_id' => $order->id,
            'type' => $type,
            'occurred_at' => now()->toIso8601String(),
        ]);
    }

    private function paidOrder(TicketBatch $batch, int $quantity = 3): Order
    {
        $order = $this->placeOrder($batch, $quantity);
        $this->notify($order, 'approved')->assertStatus(Response::HTTP_OK);

        return $order->refresh();
    }

    public function test_refund_returns_the_tickets_to_the_batch_and_invalidates_the_issued_ones(): void
    {
        $batch = TicketBatch::factory()->create(['price' => '50.00', 'total_quantity' => 10]);
        $order = $this->paidOrder($batch, 3);

        $issued = $order->tickets;
        $this->assertCount(3, $issued);

        $this->travel(1)->second();
        $this->notify($order, 'refunded')->assertStatus(Response::HTTP_OK);

        $this->assertSame(OrderStatus::Refunded, $order->refresh()->status);

        $batch->refresh();
        $this->assertSame(0, $batch->sold_quantity);
        $this->assertSame(0, $batch->reserved_quantity);
        $this->assertSame(10, $batch->availableQuantity());
        $this->assertSame('0.00', $batch->revenue);

        // Os ingressos continuam registrados, mas nenhum deles vale mais.
        $this->assertCount(3, $order->tickets()->get());
        $this->assertCount(0, $order->tickets()->valid()->get());

        foreach ($issued as $ticket) {
            $this->assertFalse($ticket->refresh()->isValid());
        }
    }

    public function test_the_same_refund_delivered_twice_returns_the_tickets_once(): void
    {
        $batch = TicketBatch::factory()->create(['price' => '50.00', 'total_quantity' => 10]);
        $first = $this->paidOrder($batch, 3);
        $second = $this->paidOrder($batch, 2);

        $this->travel(1)->second();
        $externalId = 'evt_reembolso_repetido';

        $this->notify($first, 'refunded', $externalId)->assertStatus(Response::HTTP_OK);
        $this->notify($first, 'refunded', $externalId)->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseCount('gateway_notifications', 3);

        // Só os 3 ingressos do primeiro pedido voltaram; o segundo segue vendido.
        $batch->refresh();
        $this->assertSame(2, $batch->sold_quantity);
        $this->assertSame('100.00', $batch->revenue);
        $this->assertSame(OrderStatus::Paid, $second->refresh()->status);
    }

    public function test_returned_tickets_can_be_sold_again(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 3]);
        $order = $this->paidOrder($batch, 3);

        $this->assertSame(0, $batch->refresh()->availableQuantity());

        $this->travel(1)->second();
        $this->notify($order, 'refunded')->assertStatus(Response::HTTP_OK);

        $resold = $this->placeOrder($batch, 3);

        $this->assertSame(OrderStatus::Pending, $resold->status);
        $this->assertSame(3, $batch->refresh()->reserved_quantity);
    }

    public function test_refund_of_an_unpaid_order_is_not_applied(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 10]);
        $order = $this->placeOrder($batch, 3);

        $this->notify($order, 'refunded')->assertStatus(Response::HTTP_OK);

        // Nem o estoque vendido fica negativo, nem o pedido muda de situação.
        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);

        $batch->refresh();
        $this->assertSame(0, $batch->sold_quantity);
        $this->assertSame(3, $batch->reserved_quantity);
        $this->assertTrue(GatewayNotification::sole()->isUnresolved());
    }

    public function test_tickets_are_not_issued_after_a_refund(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 10]);
        $order = $this->placeOrder($batch, 3);

        // Retém só a emissão: os avisos seguem sendo processados normalmente.
        Queue::fake([IssueTickets::class]);

        $this->notify($order, 'approved')->assertStatus(Response::HTTP_OK);
        $this->travel(1)->second();
        $this->notify($order, 'refunded')->assertStatus(Response::HTTP_OK);

        // A emissão só chega ao worker agora, depois do reembolso já aplicado.
        (new IssueTickets($order->refresh()))->handle();

        $this->assertSame(OrderStatus::Refunded, $order->refresh()->status);
        $this->assertCount(0, $order->tickets()->get());
        $this->assertNull($order->tickets_issued_at);
    }
}
