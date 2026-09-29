<?php

namespace App\Jobs;

use App\Models\GatewayNotification;
use App\Services\GatewayNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessGatewayNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [1, 5, 15, 30];

    public function __construct(private readonly GatewayNotification $notification) {}

    /**
     * Applies the order's pending notifications, outside the gateway's request.
     *
     * The job takes the whole order, not just this notification: a notification delivered
     * early stays unresolved until an older one makes its transition reachable.
     */
    public function handle(GatewayNotificationService $notifications): void
    {
        $notifications->apply($this->notification->order);
    }
}
