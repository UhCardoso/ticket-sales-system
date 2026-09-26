<?php

namespace Tests\Feature;

use App\Exceptions\BatchSoldOutException;
use App\Models\Order;
use App\Models\TicketBatch;
use App\Services\OrderService;
use Closure;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

/**
 * Compras realmente simultâneas: cada compra roda num processo filho com a própria
 * conexão MySQL. Usa DatabaseTruncation (e não RefreshDatabase) porque os filhos
 * precisam enxergar dados commitados.
 */
class ConcurrentOrderTest extends TestCase
{
    use DatabaseTruncation;

    private const BUYERS = 20;

    private const STOCK = 5;

    protected function tearDown(): void
    {
        // DatabaseTruncation só limpa antes do teste; sem isso os testes com
        // RefreshDatabase que rodam depois herdariam os pedidos commitados aqui.
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    private function purchaseData(int $batchId, int $quantity = 1): array
    {
        return [
            'ticket_batch_id' => $batchId,
            'quantity' => $quantity,
            'buyer_name' => 'Comprador',
            'buyer_email' => 'comprador@example.com',
            'buyer_document' => '12345678909',
        ];
    }

    public function test_concurrent_purchases_never_exceed_batch_total(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => self::STOCK]);

        $results = $this->runInParallel(self::BUYERS, fn () => app(OrderService::class)
            ->create($this->purchaseData($batch->id), (string) Str::uuid())->id);

        $soldOut = array_filter($results, fn ($result) => $result === BatchSoldOutException::class);
        $this->assertCount(self::BUYERS - self::STOCK, $soldOut);

        $batch->refresh();
        $this->assertSame(self::STOCK, Order::count());
        $this->assertSame(self::STOCK, $batch->reserved_quantity);
        $this->assertSame(0, $batch->availableQuantity());
    }

    public function test_simultaneous_requests_with_same_key_create_a_single_order(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 10]);
        $key = (string) Str::uuid();

        $results = $this->runInParallel(10, fn () => app(OrderService::class)
            ->create($this->purchaseData($batch->id, 2), $key)->id);

        // Every "click" got the same order back, none got an error.
        $order = Order::sole();
        $this->assertSame(array_fill(0, 10, (string) $order->id), $results);
        $this->assertSame(2, $batch->refresh()->reserved_quantity);
    }

    public function test_duplicate_committed_after_key_lookup_is_caught_by_unique_index(): void
    {
        $batch = TicketBatch::factory()->create(['total_quantity' => 10]);
        $key = (string) Str::uuid();
        $data = $this->purchaseData($batch->id, 2);

        // Simulates the other click committing its order after our key lookup found
        // nothing, but before our insert: exactly the window only the unique index covers.
        config(['database.connections.rival' => config('database.connections.mysql')]);
        $rivalId = null;

        Event::listen(TransactionBeginning::class, function () use (&$rivalId, $batch, $key, $data) {
            $rivalId ??= DB::connection('rival')->table('orders')->insertGetId([
                ...$data,
                'idempotency_key' => $key,
                'status' => 'pending',
                'unit_price' => $batch->price,
                'total' => bcmul($batch->price, '2', 2),
                'expires_at' => now()->addMinutes(15),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $order = app(OrderService::class)->create($data, $key);

        $this->assertSame($rivalId, $order->id);
        $this->assertSame(1, Order::count());
        // Our transaction rolled back, so its reservation was undone too.
        // (The rival row was inserted raw, without reserving.)
        $this->assertSame(0, $batch->refresh()->reserved_quantity);
    }

    /**
     * Runs $task in $processes forked children released at the same instant.
     * Returns each child's result: the task's return value, or the exception class it threw.
     *
     * @return list<string>
     */
    private function runInParallel(int $processes, Closure $task): array
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requer a extensão pcntl.');
        }

        // Os filhos não podem herdar o socket do pai: fechar a conexão de um fecharia a do outro.
        DB::disconnect();

        $resultsDir = sys_get_temp_dir().'/parallel-'.Str::uuid();
        mkdir($resultsDir);
        $startAt = microtime(true) + 0.5;
        $pids = [];

        for ($i = 0; $i < $processes; $i++) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                // Barreira: todos os filhos disparam no mesmo instante.
                time_sleep_until($startAt);

                try {
                    $result = (string) $task();
                } catch (Throwable $e) {
                    $result = $e::class;
                }

                file_put_contents("{$resultsDir}/{$i}", $result);

                // SIGKILL evita que o filho execute o shutdown do PHPUnit herdado do pai.
                posix_kill(getmypid(), SIGKILL);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $results = [];
        for ($i = 0; $i < $processes; $i++) {
            $results[] = (string) @file_get_contents("{$resultsDir}/{$i}");
            @unlink("{$resultsDir}/{$i}");
        }
        rmdir($resultsDir);

        return $results;
    }
}
