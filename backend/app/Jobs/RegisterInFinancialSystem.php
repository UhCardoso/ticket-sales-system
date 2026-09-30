<?php

namespace App\Jobs;

use App\Contracts\FinancialSystemInterface;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegisterInFinancialSystem implements ShouldQueue
{
    use Queueable;

    /**
     * The job will be attempted 10 times, with increasing backoff intervals.
     */
    public int $tries = 10;

    public array $backoff = [5, 15, 30, 60, 120, 300, 600];

    /**
     * Runs on its own queue and worker: at 2–5s per call, sharing the default queue would
     * hold the tickets and e-mails of every sale behind the financial system.
     */
    public function __construct(private readonly Order $order)
    {
        $this->onQueue('financial');
    }

    /**
     * One run at a time per order, so two deliveries of this job cannot both pass the
     * marker check and register twice.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->order->id))->releaseAfter(10)->expireAfter(120)];
    }

    /**
     * Registers the sale in the client's financial system, once.
     *
     * Two layers keep it single: the financial_registered_at marker skips a retry after a
     * saved success, and the order id sent as idempotency key covers a success lost
     * before the marker was saved. An order refunded in the meantime is still registered:
     * the sale did happen.
     */
    public function handle(FinancialSystemInterface $financialSystem): void
    {
        $order = $this->order->refresh();

        if ($order->financial_registered_at !== null || $order->paid_at === null) {
            return;
        }

        $reference = $financialSystem->register($order);

        $order->update([
            'financial_registered_at' => now(),
            'financial_reference' => $reference,
        ]);
    }

    /**
     * Flags a sale left unregistered after every attempt. The orders:reconcile command
     * dispatches it again later; the log makes a prolonged outage visible meanwhile.
     */
    public function failed(Throwable $exception): void
    {
        Log::critical('Venda ainda não registrada no sistema financeiro após todas as tentativas; a reconciliação tentará de novo.', [
            'order_id' => $this->order->id,
            'reason' => $exception->getMessage(),
        ]);
    }
}
