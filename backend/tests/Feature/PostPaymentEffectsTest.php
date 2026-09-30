<?php

namespace Tests\Feature;

use App\Exceptions\FinancialSystemUnavailableException;
use App\Jobs\IssueTickets;
use App\Jobs\RegisterInFinancialSystem;
use App\Jobs\SendReceiptEmail;
use App\Jobs\SendTicketsEmail;
use App\Mail\PaymentReceiptMail;
use App\Mail\TicketsMail;
use App\Models\Order;
use App\Models\TicketBatch;
use App\Services\OrderService;
use App\Tickets\TicketPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostPaymentEffectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function placeOrder(TicketBatch $batch, int $quantity = 3): Order
    {
        return app(OrderService::class)->create([
            'ticket_batch_id' => $batch->id,
            'quantity' => $quantity,
            'buyer_name' => 'Maria Silva',
            'buyer_email' => 'maria@example.com',
            'buyer_document' => '12345678909',
        ], (string) Str::uuid());
    }

    private function notify(Order $order, string $type)
    {
        return $this->postJson('/api/gateway/notifications', [
            'external_id' => 'evt_'.Str::lower(Str::random(20)),
            'order_id' => $order->id,
            'type' => $type,
            'occurred_at' => now()->toIso8601String(),
        ]);
    }

    private function paidOrder(int $quantity = 3): Order
    {
        $order = $this->placeOrder(TicketBatch::factory()->create(['total_quantity' => 10]), $quantity);
        $this->notify($order, 'approved')->assertStatus(Response::HTTP_OK);

        return $order->refresh();
    }

    public function test_approved_payment_runs_every_post_payment_effect(): void
    {
        $order = $this->paidOrder(3);

        // Um código único por ingresso.
        $this->assertCount(3, $order->tickets);
        $this->assertCount(3, $order->tickets->pluck('code')->unique());
        $this->assertNotNull($order->tickets_issued_at);

        Mail::assertSent(PaymentReceiptMail::class, fn ($mail) => $mail->hasTo('maria@example.com'));
        Mail::assertSent(TicketsMail::class, fn ($mail) => $mail->hasTo('maria@example.com'));
        $this->assertNotNull($order->receipt_sent_at);
        $this->assertNotNull($order->tickets_sent_at);

        $this->assertSame(["fin_test_{$order->id}"], array_values($this->financialSystem->sales));
        $this->assertSame("fin_test_{$order->id}", $order->financial_reference);
        $this->assertNotNull($order->financial_registered_at);
    }

    public function test_tickets_pdf_is_generated_for_the_order(): void
    {
        $order = $this->paidOrder(2);

        $pdf = app(TicketPdf::class)->render($order);

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_rerunning_the_jobs_does_not_repeat_any_effect(): void
    {
        $order = $this->paidOrder(3);

        // A fila é at-least-once: simula cada job sendo entregue de novo.
        (new IssueTickets($order))->handle();
        (new SendTicketsEmail($order))->handle();
        (new SendReceiptEmail($order))->handle();
        (new RegisterInFinancialSystem($order))->handle($this->financialSystem);

        $this->assertCount(3, $order->tickets()->get());
        Mail::assertSentCount(2);
        $this->assertSame(1, $this->financialSystem->calls);
    }

    public function test_unstable_financial_system_ends_with_the_sale_registered_once(): void
    {
        // Retém só o financeiro, para conduzir as tentativas uma a uma.
        Queue::fake([RegisterInFinancialSystem::class]);
        $this->financialSystem->failNext(2);

        $order = $this->paidOrder();
        $job = new RegisterInFinancialSystem($order);

        foreach (range(1, 2) as $attempt) {
            try {
                $job->handle($this->financialSystem);
                $this->fail("A tentativa {$attempt} deveria ter falhado.");
            } catch (FinancialSystemUnavailableException) {
                $this->assertNull($order->refresh()->financial_registered_at);
            }
        }

        $job->handle($this->financialSystem);
        $job->handle($this->financialSystem);

        $this->assertSame(3, $this->financialSystem->calls);
        $this->assertCount(1, $this->financialSystem->sales);
        $this->assertNotNull($order->refresh()->financial_registered_at);

        // A instabilidade do financeiro não reenviou nenhum e-mail.
        Mail::assertSentCount(2);
    }

    public function test_financial_retry_after_a_lost_success_does_not_register_twice(): void
    {
        $order = $this->paidOrder();

        // O financeiro registrou, mas o job morreu antes de gravar o marcador.
        $order->update(['financial_registered_at' => null, 'financial_reference' => null]);

        (new RegisterInFinancialSystem($order))->handle($this->financialSystem);

        $this->assertSame(2, $this->financialSystem->calls);
        $this->assertCount(1, $this->financialSystem->sales);
        $this->assertSame("fin_test_{$order->id}", $order->refresh()->financial_reference);
    }

    public function test_e_mails_are_not_sent_for_an_order_refunded_before_they_ran(): void
    {
        Queue::fake([IssueTickets::class, SendReceiptEmail::class]);

        $order = $this->paidOrder();
        $this->travel(1)->second();
        $this->notify($order, 'refunded')->assertStatus(Response::HTTP_OK);

        (new IssueTickets($order))->handle();
        (new SendTicketsEmail($order))->handle();
        (new SendReceiptEmail($order))->handle();

        Mail::assertNothingSent();
        $this->assertNull($order->refresh()->tickets_sent_at);
        $this->assertNull($order->receipt_sent_at);
    }
}
