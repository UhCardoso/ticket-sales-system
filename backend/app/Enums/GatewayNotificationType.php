<?php

namespace App\Enums;

enum GatewayNotificationType: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Refunded = 'refunded';

    /**
     * The order status this kind of notification moves the order to.
     */
    public function toOrderStatus(): OrderStatus
    {
        return match ($this) {
            self::Approved => OrderStatus::Paid,
            self::Rejected => OrderStatus::Rejected,
            self::Refunded => OrderStatus::Refunded,
        };
    }
}
