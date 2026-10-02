<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\TicketBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The counters are not fillable on purpose — only the stock transitions move them —
     * so the factory, which runs unguarded, is what seeds them straight into a batch.
     */
    private function batch(Event $event, array $counters): TicketBatch
    {
        return TicketBatch::factory()->for($event)->create($counters);
    }

    public function test_summarises_sold_reserved_available_and_revenue_per_batch(): void
    {
        $event = Event::factory()->create(['name' => 'Show de Rock']);
        $this->batch($event, [
            'name' => '1º Lote',
            'price' => '50.00',
            'total_quantity' => 30,
            'reserved_quantity' => 2,
            'sold_quantity' => 10,
            'revenue' => '500.00',
        ]);

        $response = $this->getJson('/api/dashboard/sales-summary');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.0.name', 'Show de Rock')
            ->assertJsonPath('data.0.batches.0.name', '1º Lote')
            ->assertJsonPath('data.0.batches.0.total_quantity', 30)
            ->assertJsonPath('data.0.batches.0.sold_quantity', 10)
            ->assertJsonPath('data.0.batches.0.reserved_quantity', 2)
            ->assertJsonPath('data.0.batches.0.available_quantity', 18)
            ->assertJsonPath('data.0.batches.0.revenue', '500.00');
    }

    public function test_event_totals_add_up_every_batch(): void
    {
        $event = Event::factory()->create();
        $this->batch($event, [
            'name' => '1º Lote',
            'total_quantity' => 30,
            'reserved_quantity' => 2,
            'sold_quantity' => 10,
            'revenue' => '500.10',
        ]);
        $this->batch($event, [
            'name' => '2º Lote',
            'total_quantity' => 50,
            'reserved_quantity' => 1,
            'sold_quantity' => 2,
            'revenue' => '140.20',
        ]);

        $this->getJson('/api/dashboard/sales-summary')
            ->assertJsonPath('data.0.totals.total_quantity', 80)
            ->assertJsonPath('data.0.totals.sold_quantity', 12)
            ->assertJsonPath('data.0.totals.reserved_quantity', 3)
            ->assertJsonPath('data.0.totals.available_quantity', 65)
            ->assertJsonPath('data.0.totals.revenue', '640.30');
    }

    public function test_serves_repeated_requests_from_cache_without_touching_the_database(): void
    {
        $event = Event::factory()->create();
        $this->batch($event, ['total_quantity' => 30, 'sold_quantity' => 10, 'revenue' => '500.00']);

        $this->getJson('/api/dashboard/sales-summary')->assertOk();

        // O painel é polling de dezenas de clientes: a partir do primeiro, ninguém mais
        // chega ao MySQL, que no pico está sob contenção de escrita do caminho de compra.
        DB::enableQueryLog();
        $this->getJson('/api/dashboard/sales-summary')
            ->assertOk()
            ->assertJsonPath('data.0.batches.0.sold_quantity', 10);

        $this->assertEmpty(DB::getQueryLog());
    }

    public function test_dates_the_snapshot_not_the_request(): void
    {
        Event::factory()->create();

        $first = $this->getJson('/api/dashboard/sales-summary')->assertOk();

        $this->travel(2)->seconds();

        $second = $this->getJson('/api/dashboard/sales-summary')->assertOk();

        $this->assertSame(
            $first->json('meta.generated_at'),
            $second->json('meta.generated_at'),
            'generated_at deve datar o snapshot em cache, não o request que o encontrou.',
        );
    }

    public function test_returns_an_empty_list_when_there_are_no_events(): void
    {
        $this->getJson('/api/dashboard/sales-summary')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
