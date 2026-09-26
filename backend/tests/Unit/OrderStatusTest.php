<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public static function transitions(): array
    {
        return [
            'pending → paid' => [OrderStatus::Pending, OrderStatus::Paid, true],
            'pending → rejected' => [OrderStatus::Pending, OrderStatus::Rejected, true],
            'pending → expired' => [OrderStatus::Pending, OrderStatus::Expired, true],
            'paid → refunded' => [OrderStatus::Paid, OrderStatus::Refunded, true],
            'pending → refunded' => [OrderStatus::Pending, OrderStatus::Refunded, false],
            'paid → expired' => [OrderStatus::Paid, OrderStatus::Expired, false],
            'expired → paid' => [OrderStatus::Expired, OrderStatus::Paid, false],
            'rejected → paid' => [OrderStatus::Rejected, OrderStatus::Paid, false],
            'refunded → paid' => [OrderStatus::Refunded, OrderStatus::Paid, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_allowed_transitions(OrderStatus $from, OrderStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }
}
