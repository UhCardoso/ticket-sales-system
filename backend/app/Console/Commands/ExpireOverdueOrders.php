<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Throwable;

class ExpireOverdueOrders extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Expire unpaid orders past their deadline and release their reserved tickets';

    public function handle(OrderService $orders): int
    {
        $expired = 0;

        Order::overdue()->lazyById(100)->each(function (Order $order) use ($orders, &$expired) {
            try {
                $expired += (int) $orders->expire($order);
            } catch (Throwable $e) {
                // One failing order must not hold back the others; it is retried on the next run.
                report($e);
            }
        });

        $this->info("{$expired} order(s) expired.");

        return self::SUCCESS;
    }
}
