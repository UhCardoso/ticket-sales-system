<?php

namespace Tests\Feature;

use App\Exceptions\BatchSoldOutException;
use App\Models\Order;
use App\Models\TicketBatch;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

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

    public function test_concurrent_purchases_never_exceed_batch_total(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requer a extensão pcntl.');
        }

        $batch = TicketBatch::factory()->create(['total_quantity' => self::STOCK]);

        // Os filhos não podem herdar o socket do pai: fechar a conexão de um fecharia a do outro.
        DB::disconnect();

        $startAt = microtime(true) + 0.5;
        $pids = [];

        for ($i = 0; $i < self::BUYERS; $i++) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                $this->buyInChildProcess($batch->id, $startAt);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $batch->refresh();
        $this->assertSame(self::STOCK, Order::count());
        $this->assertSame(self::STOCK, $batch->reserved_quantity);
        $this->assertSame(0, $batch->availableQuantity());
    }

    private function buyInChildProcess(int $batchId, float $startAt): never
    {
        // Barreira: todos os filhos disparam a compra no mesmo instante.
        time_sleep_until($startAt);

        try {
            app(OrderService::class)->create([
                'ticket_batch_id' => $batchId,
                'quantity' => 1,
                'buyer_name' => 'Comprador',
                'buyer_email' => 'comprador@example.com',
                'buyer_document' => '12345678909',
            ], (string) Str::uuid());
        } catch (BatchSoldOutException) {
            // esperado para quem chegou depois do estoque acabar
        }

        // SIGKILL evita que o filho execute o shutdown do PHPUnit herdado do pai.
        posix_kill(getmypid(), SIGKILL);
        exit;
    }
}
