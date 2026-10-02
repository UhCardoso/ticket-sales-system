<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SalesDashboardService
{
    /**
     * Returns the sales snapshot of every event with its batches, cached for a few seconds.
     *
     * Reads the denormalized counters on the batches, never an aggregate over orders, and
     * never takes a lock: the dashboard tolerates a few seconds of delay, while dozens of
     * clients polling through the lock queue would stall the purchase path itself.
     *
     * `generated_at` is produced inside the closure, so it dates the snapshot and not the
     * request that happened to find it already cached — it is what lets the panel state
     * how stale it is instead of implying it is live.
     *
     * @return array{generated_at: Carbon, events: Collection<int, Event>}
     */
    public function summary(): array
    {
        return Cache::remember(
            config('dashboard.cache_key'),
            config('dashboard.cache_ttl'),
            fn () => [
                'generated_at' => now(),
                'events' => Event::with(['batches' => fn ($batches) => $batches->orderBy('id')])
                    ->orderBy('date_time')
                    ->get(),
            ],
        );
    }
}
