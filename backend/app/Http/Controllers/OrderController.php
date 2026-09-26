<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Creates an order. Returns 201 for a new order, 200 for a repeated Idempotency-Key.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $order = $this->orders->create(
            Arr::except($validated, 'idempotency_key'),
            $validated['idempotency_key'],
        );

        return OrderResource::make($order)
            ->response()
            ->setStatusCode($order->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
