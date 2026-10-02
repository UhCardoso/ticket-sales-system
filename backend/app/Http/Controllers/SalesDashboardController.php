<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventSalesSummaryResource;
use App\Services\SalesDashboardService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SalesDashboardController extends Controller
{
    public function __construct(private readonly SalesDashboardService $dashboard) {}

    /**
     * Returns the sales summary per event and batch, served from the cached snapshot.
     *
     * The meta carries the snapshot's age and TTL so the panel can show how stale the
     * numbers are rather than presenting them as live.
     */
    public function index(): AnonymousResourceCollection
    {
        $summary = $this->dashboard->summary();

        return EventSalesSummaryResource::collection($summary['events'])
            ->additional([
                'meta' => [
                    'generated_at' => $summary['generated_at']->toIso8601String(),
                    'cache_ttl' => config('dashboard.cache_ttl'),
                ],
            ]);
    }
}
