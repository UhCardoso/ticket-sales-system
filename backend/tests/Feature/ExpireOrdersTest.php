<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\TicketBatch;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpireOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function placeOrder(TicketBatch $batch, int $quantity): Order
    {
        return app(OrderService::class)->create([
            'ticket_batch_id' => $batch->id,
            'quantity' => $quantity,
            'buyer_name' => 'Maria Silva',
            'buyer_email' => 'maria@example.com',
            'buyer_document' => '12345678909',
        ], (string) Str::uuid());
    }

    public function test_overdue_order_is_expired_and_releases_its_tickets(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 10]);
        $order = $this->placeOrder($batch, 3);

        $this->travel(config('orders.reservation_ttl') + 1)->minutes();
        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(OrderStatus::Expired, $order->refresh()->status);
        $this->assertSame(0, $batch->refresh()->reserved_quantity);
        $this->assertSame(10, $batch->availableQuantity());
    }

    public function test_order_within_deadline_keeps_its_reservation(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 10]);
        $order = $this->placeOrder($batch, 3);

        $this->travel(config('orders.reservation_ttl') - 1)->minutes();
        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
        $this->assertSame(3, $batch->refresh()->reserved_quantity);
    }

    public function test_released_tickets_can_be_bought_again(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 2]);
        $this->placeOrder($batch, 2);

        $payload = [
            'ticket_batch_id' => $batch->id,
            'quantity' => 2,
            'buyer_name' => 'João Souza',
            'buyer_email' => 'joao@example.com',
            'buyer_document' => '98765432100',
        ];

        $this->postJson('/api/orders', $payload, ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(Response::HTTP_CONFLICT);

        $this->travel(config('orders.reservation_ttl') + 1)->minutes();
        $this->artisan('orders:expire');

        $this->postJson('/api/orders', $payload, ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(Response::HTTP_CREATED);

        $this->assertSame(2, $batch->refresh()->reserved_quantity);
    }

    public function test_expiring_an_order_twice_releases_stock_once(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 10]);
        $order = $this->placeOrder($batch, 3);
        $this->placeOrder($batch, 2);

        $this->travel(config('orders.reservation_ttl') + 1)->minutes();

        $this->assertTrue(app(OrderService::class)->expire($order));
        $this->assertFalse(app(OrderService::class)->expire($order));

        // Only the first order's 3 tickets were released; the other order still holds 2.
        $this->assertSame(2, $batch->refresh()->reserved_quantity);
    }

    public function test_paid_order_is_not_expired(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 10]);
        $order = $this->placeOrder($batch, 3);
        $order->transitionTo(OrderStatus::Paid);

        $this->travel(config('orders.reservation_ttl') + 1)->minutes();

        $this->assertFalse(app(OrderService::class)->expire($order));
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertSame(3, $batch->refresh()->reserved_quantity);
    }
}
