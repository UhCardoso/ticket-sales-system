<?php

namespace Tests\Feature;

use App\Enums\OrderEmail;
use App\Enums\OrderStatus;
use App\Jobs\SendReceiptEmail;
use App\Models\Order;
use App\Models\TicketBatch;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\Doubles\FlakyMailTransport;
use Tests\TestCase;

class EmailInDoubtTest extends TestCase
{
    use RefreshDatabase;

    private FlakyMailTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = new FlakyMailTransport;

        Mail::extend('flaky', fn () => $this->transport);
        config(['mail.mailers.flaky' => ['transport' => 'flaky'], 'mail.default' => 'flaky']);
    }

    /**
     * A paid order whose effects were not dispatched, so each test drives the receipt job.
     */
    private function paidOrder(): Order
    {
        $order = app(OrderService::class)->create([
            'ticket_batch_id' => TicketBatch::factory()->create()->id,
            'quantity' => 1,
            'buyer_name' => 'Maria Silva',
            'buyer_email' => 'maria@example.com',
            'buyer_document' => '12345678909',
        ], (string) Str::uuid());

        $order->forceFill(['status' => OrderStatus::Paid, 'paid_at' => now()])->save();

        return $order;
    }

    public function test_failure_before_data_releases_the_claim_and_the_retry_sends_once(): void
    {
        $order = $this->paidOrder();
        $this->transport->failNextWith(FlakyMailTransport::BEFORE_DATA);

        try {
            (new SendReceiptEmail($order))->handle();
            $this->fail('A falha de transporte deveria ter subido para o retry da fila.');
        } catch (TransportException) {
            // O servidor nunca recebeu a mensagem: o envio pode ser tentado de novo.
            $this->assertNull($order->refresh()->receipt_sending_at);
        }

        (new SendReceiptEmail($order))->handle();
        (new SendReceiptEmail($order))->handle();

        $this->assertSame(2, $this->transport->attempts);
        $this->assertNotNull($order->refresh()->receipt_sent_at);
    }

    public function test_failure_after_data_leaves_the_e_mail_in_doubt_and_never_resends_it(): void
    {
        Log::spy();

        $order = $this->paidOrder();
        $this->transport->failNextWith(FlakyMailTransport::AFTER_DATA);

        // Não sobe exceção: um retry da fila é justamente o que não pode acontecer.
        (new SendReceiptEmail($order))->handle();
        (new SendReceiptEmail($order))->handle();

        $this->assertSame(1, $this->transport->attempts);
        $this->assertTrue($order->refresh()->isEmailInDoubt(OrderEmail::Receipt));
        Log::shouldHaveReceived('warning')->twice();
    }

    public function test_a_worker_dying_mid_send_leaves_the_e_mail_in_doubt(): void
    {
        $order = $this->paidOrder();

        // O worker reivindicou o envio e morreu antes de confirmar.
        $order->update(['receipt_sending_at' => now()]);

        (new SendReceiptEmail($order))->handle();

        $this->assertSame(0, $this->transport->attempts);
        $this->assertNull($order->refresh()->receipt_sent_at);
    }
}
