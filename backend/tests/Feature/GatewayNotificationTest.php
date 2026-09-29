<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\ProcessGatewayNotification;
use App\Models\GatewayNotification;
use App\Models\Order;
use App\Models\TicketBatch;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class GatewayNotificationTest extends TestCase
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

    /**
     * Entrega um aviso como o gateway faria. Repetir a chamada com o mesmo $externalId
     * reproduz uma reentrega; $occurredAt controla quando o aviso aconteceu.
     */
    private function notify(Order $order, string $type, ?string $externalId = null, ?Carbon $occurredAt = null)
    {
        return $this->postJson('/api/gateway/notifications', [
            'external_id' => $externalId ?? 'evt_'.Str::lower(Str::random(20)),
            'order_id' => $order->id,
            'type' => $type,
            'occurred_at' => ($occurredAt ?? now())->toIso8601String(),
        ]);
    }

    public function test_approved_notification_turns_the_reservation_into_a_sale(): void
    {
        $batch = TicketBatch::factory()->create(['price' => '50.00', 'total_quantity' => 10]);
        $order = $this->placeOrder($batch, 3);

        $this->notify($order, 'approved')->assertStatus(Response::HTTP_OK);

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);

        $batch->refresh();
        $this->assertSame(0, $batch->reserved_quantity);
        $this->assertSame(3, $batch->sold_quantity);
        $this->assertSame(7, $batch->availableQuantity());
        $this->assertSame('150.00', $batch->revenue);

        $this->assertCount(3, $order->tickets()->valid()->get());
    }

    public function test_rejected_notification_releases_the_reservation(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 10]);
        $order = $this->placeOrder($batch, 3);

        $this->notify($order, 'rejected')->assertStatus(Response::HTTP_OK);

        $this->assertSame(OrderStatus::Rejected, $order->refresh()->status);

        $batch->refresh();
        $this->assertSame(0, $batch->reserved_quantity);
        $this->assertSame(0, $batch->sold_quantity);
        $this->assertSame('0.00', $batch->revenue);
        $this->assertCount(0, $order->tickets);
    }

    public function test_the_same_notification_delivered_twice_is_applied_once(): void
    {
        $batch = TicketBatch::factory()->create(['price' => '50.00', 'total_quantity' => 10]);
        $order = $this->placeOrder($batch, 3);
        $externalId = 'evt_repetido';

        $first = $this->notify($order, 'approved', $externalId)->assertStatus(Response::HTTP_OK);
        $second = $this->notify($order, 'approved', $externalId)->assertStatus(Response::HTTP_OK);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('gateway_notifications', 1);

        $batch->refresh();
        $this->assertSame(3, $batch->sold_quantity);
        $this->assertSame('150.00', $batch->revenue);
        $this->assertCount(3, $order->tickets);
    }

    public function test_the_request_only_records_and_enqueues(): void
    {
        Queue::fake();

        $batch = TicketBatch::factory()->create(['total_quantity' => 10]);
        $order = $this->placeOrder($batch, 3);

        $this->notify($order, 'approved')->assertStatus(Response::HTTP_OK);

        // Nada do fluxo pós-pagamento acontece no ciclo do request: o gateway considera
        // falha qualquer resposta acima de 3s.
        Queue::assertPushed(ProcessGatewayNotification::class, 1);
        $this->assertDatabaseCount('gateway_notifications', 1);
        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
        $this->assertSame(3, $batch->refresh()->reserved_quantity);
    }

    public function test_older_notification_arriving_later_does_not_overwrite_newer_state(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 10]);
        $order = $this->placeOrder($batch, 3);

        $this->notify($order, 'approved', occurredAt: now());
        $this->notify($order, 'rejected', occurredAt: now()->subHour())->assertStatus(Response::HTTP_OK);

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertSame(3, $batch->refresh()->sold_quantity);

        $stale = GatewayNotification::where('type', 'rejected')->sole();
        $this->assertNull($stale->applied_at);
        $this->assertSame(GatewayNotification::DISCARD_STALE, $stale->discard_reason);
    }

    public function test_refund_delivered_before_the_approval_still_ends_refunded(): void
    {
        $batch = TicketBatch::factory()->create(['price' => '50.00', 'total_quantity' => 10]);
        $order = $this->placeOrder($batch, 3);

        // O reembolso aconteceu depois, mas chega primeiro.
        $this->notify($order, 'refunded', occurredAt: now())->assertStatus(Response::HTTP_OK);

        // Enquanto a aprovação não chega, o reembolso fica registrado e não aplicado.
        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
        $refund = GatewayNotification::where('type', 'refunded')->sole();
        $this->assertTrue($refund->isUnresolved());

        $this->notify($order, 'approved', occurredAt: now()->subSeconds(30))->assertStatus(Response::HTTP_OK);

        $this->assertSame(OrderStatus::Refunded, $order->refresh()->status);
        $this->assertNotNull($refund->refresh()->applied_at);

        $batch->refresh();
        $this->assertSame(0, $batch->reserved_quantity);
        $this->assertSame(0, $batch->sold_quantity);
        $this->assertSame('0.00', $batch->revenue);
        $this->assertCount(0, $order->tickets()->valid()->get());
    }

    public function test_notification_incoherent_with_a_final_status_is_recorded_but_not_applied(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 10]);
        $order = $this->placeOrder($batch, 3);

        $this->travel(config('orders.reservation_ttl') + 1)->minutes();
        $this->artisan('orders:expire')->assertSuccessful();

        // Pagamento aprovado depois da reserva ter sido liberada: não há como aplicar.
        $this->notify($order, 'approved')->assertStatus(Response::HTTP_OK);

        $this->assertSame(OrderStatus::Expired, $order->refresh()->status);
        $this->assertSame(0, $batch->refresh()->sold_quantity);

        $notification = GatewayNotification::sole();
        $this->assertNull($notification->applied_at);
        $this->assertSame(GatewayNotification::DISCARD_UNREACHABLE, $notification->discard_reason);
    }

    public function test_notification_for_an_unknown_order_is_rejected(): void
    {
        $this->postJson('/api/gateway/notifications', [
            'external_id' => 'evt_orfao',
            'order_id' => 999,
            'type' => 'approved',
            'occurred_at' => now()->toIso8601String(),
        ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('order_id');

        $this->assertDatabaseCount('gateway_notifications', 0);
    }

    public function test_notification_with_missing_or_invalid_fields_is_rejected(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 10]);
        $order = $this->placeOrder($batch, 3);

        $this->postJson('/api/gateway/notifications', [
            'order_id' => $order->id,
            'type' => 'chargeback',
            'occurred_at' => 'ontem',
        ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['external_id', 'type', 'occurred_at']);
    }
}
