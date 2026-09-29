<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = ['order_id', 'code', 'invalidated_at'];

    /**
     * Attribute casts for the ticket.
     */
    protected function casts(): array
    {
        return [
            'invalidated_at' => 'datetime',
        ];
    }

    /**
     * Order this ticket belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Tickets that still grant entry.
     */
    public function scopeValid(Builder $query): void
    {
        $query->whereNull('invalidated_at');
    }

    /**
     * Whether the ticket is still valid.
     */
    public function isValid(): bool
    {
        return $this->invalidated_at === null;
    }
}
