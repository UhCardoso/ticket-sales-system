<?php

namespace App\Jobs;

use App\Enums\OrderEmail;
use App\Enums\OrderStatus;
use App\Mail\PaymentReceiptMail;
use App\Models\Order;
use Illuminate\Mail\Mailable;

class SendReceiptEmail extends SendOrderEmail
{
    /**
     * The payment receipt.
     */
    protected function email(): OrderEmail
    {
        return OrderEmail::Receipt;
    }

    /**
     * Only for an order still paid: a refund arriving first makes the receipt moot.
     */
    protected function shouldSend(Order $order): bool
    {
        return $order->status === OrderStatus::Paid;
    }

    /**
     * Receipt with the order's payment details.
     */
    protected function mailable(Order $order): Mailable
    {
        return new PaymentReceiptMail($order);
    }
}
