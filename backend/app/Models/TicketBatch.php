<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Exception;

class TicketBatch extends Model
{
    protected $fillable = ['event_id', 'name', 'price', 'total_quantity', 'sold_quantity'];

    protected static function booted(): void
    {
        static::creating(function (TicketBatch $batch) {
            if (is_null($batch->remaining_quantity)) {
                $batch->remaining_quantity = $batch->total_quantity;
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function hasAvailable(): bool
    {
        return $this->remaining_quantity > 0;
    }

    public function sellOne(): void
    {
        DB::transaction(function () {
            $batch = static::query()->lockForUpdate()->findOrFail($this->id);

            if (!$batch->hasAvailable()) {
                throw new Exception("Este lote ({$batch->name}) está esgotado!");
            }

            $batch->increment('sold_quantity');
            $batch->decrement('remaining_quantity');

            $this->sold_quantity = $batch->sold_quantity;
            $this->remaining_quantity = $batch->remaining_quantity;
        });
    }
}
