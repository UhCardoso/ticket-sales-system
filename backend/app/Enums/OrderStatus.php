<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Refunded = 'refunded';
}
