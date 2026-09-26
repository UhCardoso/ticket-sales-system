<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\TicketBatch;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreateOrderTest extends TestCase
{
    use RefreshDatabase;

    private function payload(TicketBatch $batch, array $overrides = []): array
    {
        return [
            'ticket_batch_id' => $batch->id,
            'quantity' => 2,
            'buyer_name' => 'Maria Silva',
            'buyer_email' => 'maria@example.com',
            'buyer_document' => '123.456.789-09',
            ...$overrides,
        ];
    }

    private function purchase(array $payload, ?string $key = null)
    {
        return $this->postJson('/api/orders', $payload, ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    public function test_creates_pending_order_and_reserves_stock(): void
    {
        $batch = TicketBatch::factory()->create(['price' => '49.90', 'total_quantity' => 10]);

        $response = $this->purchase($this->payload($batch, ['quantity' => 3]));

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.quantity', 3)
            ->assertJsonPath('data.unit_price', '49.90')
            ->assertJsonPath('data.total', '149.70')
            ->assertJsonPath('data.buyer.document', '12345678909');

        $batch->refresh();
        $this->assertSame(3, $batch->reserved_quantity);
        $this->assertSame(0, $batch->sold_quantity);
        $this->assertSame(7, $batch->availableQuantity());
    }

    public function test_rejects_quantity_above_available_with_conflict(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 2]);

        $this->purchase($this->payload($batch, ['quantity' => 3]))
            ->assertStatus(Response::HTTP_CONFLICT);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, $batch->refresh()->reserved_quantity);
    }

    public function test_same_idempotency_key_returns_existing_order_without_reserving_again(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 10]);
        $key = (string) Str::uuid();

        $first = $this->purchase($this->payload($batch), $key)->assertStatus(Response::HTTP_CREATED);
        $second = $this->purchase($this->payload($batch), $key)->assertStatus(Response::HTTP_OK);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(2, $batch->refresh()->reserved_quantity);
    }

    public function test_same_idempotency_key_with_different_data_is_rejected(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 10]);
        $key = (string) Str::uuid();

        $this->purchase($this->payload($batch, ['quantity' => 2]), $key)->assertStatus(Response::HTTP_CREATED);

        $this->purchase($this->payload($batch, ['quantity' => 3]), $key)
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(2, $batch->refresh()->reserved_quantity);
    }

    public function test_requires_idempotency_key_header(): void
    {
        $batch = TicketBatch::factory()->create();

        $this->postJson('/api/orders', $this->payload($batch))
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('idempotency_key');
    }

    public function test_validates_buyer_data_and_quantity(): void
    {
        $batch = TicketBatch::factory()->create();

        $this->purchase($this->payload($batch, [
            'quantity' => 0,
            'buyer_name' => '',
            'buyer_email' => 'invalido',
            'buyer_document' => '123',
        ]))
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['quantity', 'buyer_name', 'buyer_email', 'buyer_document']);

        $this->assertSame(0, Order::count());
    }

    public function test_database_rejects_reservation_beyond_total(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 5]);

        $this->expectException(QueryException::class);

        $batch->increment('reserved_quantity', 6);
    }
}
