<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'ticket_batch_id',
        'idempotency_key',
        'status',
        'buyer_name',
        'buyer_email',
        'buyer_document',
        'quantity',
        'unit_price',
        'total',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function ticketBatch(): BelongsTo
    {
        return $this->belongsTo(TicketBatch::class);
    }

    /**
     * Verifica se os dados de uma compra são os mesmos que originaram este pedido.
     *
     * @param  array<string, mixed>  $data
     */
    public function matchesPurchase(array $data): bool
    {
        foreach ($data as $field => $value) {
            if ((string) $this->getAttribute($field) !== (string) $value) {
                return false;
            }
        }

        return true;
    }
}
