<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGatewayNotificationRequest;
use App\Http\Resources\GatewayNotificationResource;
use App\Jobs\ProcessGatewayNotification;
use App\Services\GatewayNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GatewayNotificationController extends Controller
{
    public function __construct(private readonly GatewayNotificationService $notifications) {}

    /**
     * Receives a payment notification from the gateway.
     *
     * The gateway treats any response over 3 seconds as a failure and resends, so the
     * request does nothing but store the raw notice, queue it and answer 200. Applying it
     * — stock, tickets, e-mails, financial system — happens in the worker.
     */
    public function store(StoreGatewayNotificationRequest $request): JsonResponse
    {
        $notification = $this->notifications->record($request->validated());

        if ($notification->isUnresolved()) {
            ProcessGatewayNotification::dispatch($notification)->afterCommit();
        }

        return GatewayNotificationResource::make($notification)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
