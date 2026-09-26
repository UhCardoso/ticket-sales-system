<?php

use App\Exceptions\BatchSoldOutException;
use App\Exceptions\IdempotencyKeyReusedException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (BatchSoldOutException $e) => response()->json(
            ['message' => $e->getMessage()],
            Response::HTTP_CONFLICT,
        ));

        $exceptions->render(fn (IdempotencyKeyReusedException $e) => response()->json(
            ['message' => $e->getMessage()],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ));
    })->create();
