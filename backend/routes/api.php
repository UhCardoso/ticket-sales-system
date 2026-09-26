<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\TicketBatchController;
use Illuminate\Support\Facades\Route;

Route::get('/ticket-batches', [TicketBatchController::class, 'index']);
Route::post('/orders', [OrderController::class, 'store']);
