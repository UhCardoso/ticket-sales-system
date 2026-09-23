<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = ['name', 'date_time'];

    public function batches(): HasMany
    {
        return $this->hasMany(TicketBatch::class);
    }
}
