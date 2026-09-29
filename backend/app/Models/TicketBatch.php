<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketBatch extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'name', 'price', 'total_quantity', 'revenue'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'revenue' => 'decimal:2',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The number of tickets that have been reserved but not yet sold.
     */
    public function availableQuantity(): int
    {
        return $this->total_quantity - $this->reserved_quantity - $this->sold_quantity;
    }

    /**
     * Turns a reservation into a sale and accumulates the order's revenue.
     *
     * @param  $total  The order's total, as the decimal string the cast returns
     */
    public function confirmSale(int $quantity, string $total): void
    {
        $this->decrement('reserved_quantity', $quantity);
        $this->increment('sold_quantity', $quantity);
        $this->update(['revenue' => bcadd($this->revenue, $total, 2)]);
    }

    /**
     * Gives reserved tickets back to the batch, without selling them.
     */
    public function releaseReservation(int $quantity): void
    {
        $this->decrement('reserved_quantity', $quantity);
    }

    /**
     * Undoes a sale: the tickets go back to the batch and the revenue is subtracted.
     *
     * @param  $total  The order's total, as the decimal string the cast returns
     */
    public function returnSale(int $quantity, string $total): void
    {
        $this->decrement('sold_quantity', $quantity);
        $this->update(['revenue' => bcsub($this->revenue, $total, 2)]);
    }
}
