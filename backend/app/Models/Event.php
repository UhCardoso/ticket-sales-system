<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'date_time'];

    protected function casts(): array
    {
        return [
            'date_time' => 'datetime',
        ];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(TicketBatch::class);
    }

    /**
     * The event's total capacity, across every batch.
     */
    public function totalQuantity(): int
    {
        return (int) $this->batches->sum('total_quantity');
    }

    /**
     * The number of tickets sold across every batch.
     */
    public function soldQuantity(): int
    {
        return (int) $this->batches->sum('sold_quantity');
    }

    /**
     * The number of tickets held by pending orders across every batch.
     */
    public function reservedQuantity(): int
    {
        return (int) $this->batches->sum('reserved_quantity');
    }

    /**
     * The number of tickets still on sale across every batch.
     */
    public function availableQuantity(): int
    {
        return (int) $this->batches->sum(fn (TicketBatch $batch) => $batch->availableQuantity());
    }

    /**
     * The revenue accumulated across every batch, as a decimal string.
     *
     * Accumulated with bcadd rather than sum(): adding the batches as floats loses cents
     * that only show up when the books are closed.
     */
    public function revenue(): string
    {
        return $this->batches->reduce(
            fn (string $total, TicketBatch $batch) => bcadd($total, $batch->revenue, 2),
            '0.00',
        );
    }
}
