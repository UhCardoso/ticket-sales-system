<?php

namespace App\Console\Commands;

use App\Enums\OrderEmail;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Throwable;

class ReconcilePostPaymentEffects extends Command
{
    protected $signature = 'orders:reconcile';

    protected $description = 'Dispatch again the post-payment effects that paid orders are still owed';

    /**
     * Re-dispatches owed effects and lists the e-mails left in doubt.
     *
     * The queue only speeds effects up; the markers on the order are the record. A job
     * lost with Redis, never dispatched, or out of attempts is caught here.
     */
    public function handle(OrderService $orders): int
    {
        $reconciled = 0;

        Order::withPendingEffects(now()->subMinutes(config('orders.reconcile_after')))
            ->lazyById(100)
            ->each(function (Order $order) use ($orders, &$reconciled) {
                try {
                    $orders->dispatchPendingEffects($order);
                    $reconciled++;
                } catch (Throwable $e) {
                    report($e);
                }
            });

        $this->info("{$reconciled} order(s) with pending effects dispatched again.");

        $this->reportEmailsInDoubt();

        return self::SUCCESS;
    }

    /**
     * Lists e-mails that may or may not have gone out, for a person to check.
     */
    private function reportEmailsInDoubt(): void
    {
        foreach (OrderEmail::cases() as $email) {
            $inDoubt = Order::whereNotNull($email->sendingColumn())->whereNull($email->sentColumn())->pluck('id');

            if ($inDoubt->isNotEmpty()) {
                $this->warn("E-mail {$email->value} em dúvida nos pedidos: {$inDoubt->implode(', ')}");
            }
        }
    }
}
