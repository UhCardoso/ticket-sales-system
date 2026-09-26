<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketBatchResource;
use App\Models\TicketBatch;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketBatchController extends Controller
{
    /**
     * Lists all ticket batches with their event.
     */
    public function index(): AnonymousResourceCollection
    {
        return TicketBatchResource::collection(TicketBatch::with('event')->get());
    }
}
