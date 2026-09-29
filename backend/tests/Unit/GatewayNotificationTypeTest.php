<?php

namespace Tests\Unit;

use App\Enums\GatewayNotificationType;
use App\Enums\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GatewayNotificationTypeTest extends TestCase
{
    public static function types(): array
    {
        return [
            'approved → paid' => [GatewayNotificationType::Approved, OrderStatus::Paid],
            'rejected → rejected' => [GatewayNotificationType::Rejected, OrderStatus::Rejected],
            'refunded → refunded' => [GatewayNotificationType::Refunded, OrderStatus::Refunded],
        ];
    }

    #[DataProvider('types')]
    public function test_maps_each_notification_type_to_an_order_status(
        GatewayNotificationType $type,
        OrderStatus $expected,
    ): void {
        $this->assertSame($expected, $type->toOrderStatus());
    }
}
