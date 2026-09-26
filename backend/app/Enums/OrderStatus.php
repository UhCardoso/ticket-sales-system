<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Refunded = 'refunded';

    /**
     * pending ──> paid ──> refunded
     *    ├──> rejected
     *    └──> expired
     */
    public function canTransitionTo(self $next): bool
    {
        $allowed = match ($this) {
            self::Pending => [self::Paid, self::Rejected, self::Expired],
            self::Paid => [self::Refunded],
            self::Rejected, self::Expired, self::Refunded => [],
        };

        return in_array($next, $allowed, true);
    }
}
