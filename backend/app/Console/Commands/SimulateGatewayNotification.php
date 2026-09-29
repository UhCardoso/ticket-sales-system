<?php

namespace App\Console\Commands;

use App\Enums\GatewayNotificationType;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SimulateGatewayNotification extends Command
{
    protected $signature = 'gateway:notify
        {order : ID do pedido}
        {--type=approved : Tipo do aviso: approved, rejected ou refunded}
        {--occurred-at= : Data/hora em que o aviso aconteceu (padrão: agora)}
        {--duplicate=1 : Quantas vezes entregar o mesmo aviso}
        {--out-of-order : Entrega approved e refunded na ordem inversa da que aconteceram}
        {--timeout : Ignora a resposta da primeira entrega e reenvia, como o gateway faz após 3s}';

    protected $description = 'Simulate the payment gateway delivering notifications to the application';

    /**
     * Delivers the notifications over HTTP, so the whole receiving path is exercised.
     */
    public function handle(): int
    {
        $notifications = $this->option('out-of-order')
            ? $this->outOfOrderPair()
            : $this->single();

        if ($notifications === null) {
            return self::FAILURE;
        }

        $rows = [];

        foreach ($notifications as $notification) {
            foreach ($this->deliveryLabels() as $label) {
                $rows[] = $this->deliver($notification, $label);
            }
        }

        $this->table(['Aviso', 'Tipo', 'Aconteceu em', 'Entrega', 'HTTP', 'Resultado'], $rows);
        $this->comment('Quem aplica os avisos é o worker (queue:work), não a requisição.');

        return self::SUCCESS;
    }

    /**
     * The single notification asked for on the command line.
     *
     * @return list<array<string, string>>|null
     */
    private function single(): ?array
    {
        $type = GatewayNotificationType::tryFrom((string) $this->option('type'));

        if ($type === null) {
            $this->error('Tipo inválido. Use approved, rejected ou refunded.');

            return null;
        }

        $occurredAt = $this->option('occurred-at')
            ? Carbon::parse((string) $this->option('occurred-at'))
            : now();

        return [$this->notification($type, $occurredAt)];
    }

    /**
     * An approval and a refund, delivered in the reverse order of what happened.
     *
     * The refund happened last but arrives first, which is the case the application has to
     * resolve by occurred_at: the end state must be refunded, not paid.
     *
     * @return list<array<string, string>>
     */
    private function outOfOrderPair(): array
    {
        $this->warn('Entregando o reembolso antes da aprovação, na ordem inversa da que aconteceram.');

        return [
            $this->notification(GatewayNotificationType::Refunded, now()),
            $this->notification(GatewayNotificationType::Approved, now()->subSeconds(30)),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function notification(GatewayNotificationType $type, Carbon $occurredAt): array
    {
        return [
            'external_id' => 'evt_'.Str::lower(Str::random(20)),
            'order_id' => (string) $this->argument('order'),
            'type' => $type->value,
            'occurred_at' => $occurredAt->toIso8601String(),
        ];
    }

    /**
     * One label per delivery of the same notification, in the order they are sent.
     *
     * @return list<string>
     */
    private function deliveryLabels(): array
    {
        $labels = [];

        if ($this->option('timeout')) {
            $labels[] = 'resposta ignorada';
        }

        $deliveries = max(1, (int) $this->option('duplicate'));

        for ($i = 1; $i <= $deliveries; $i++) {
            $labels[] = $labels === [] && $i === 1 ? 'entrega' : 'reentrega';
        }

        return $labels;
    }

    /**
     * @param  $payload  The notification body, identical across redeliveries
     * @return list<string>
     */
    private function deliver(array $payload, string $label): array
    {
        $status = '—';
        $result = 'sem resposta em '.config('gateway.response_timeout').'s';

        try {
            $response = Http::timeout((int) config('gateway.response_timeout'))
                ->acceptJson()
                ->post((string) config('gateway.notifications_url'), $payload);

            $status = (string) $response->status();
            $result = $this->describe($response->json());
        } catch (ConnectionException) {
            // É o que o gateway real faz: passou do limite, considera falha e reenvia.
        }

        return [$payload['external_id'], $payload['type'], $payload['occurred_at'], $label, $status, $result];
    }

    /**
     * Turns the endpoint's answer into one readable line.
     */
    private function describe(?array $body): string
    {
        if ($body === null) {
            return 'resposta vazia';
        }

        if (isset($body['message'])) {
            return $body['message'];
        }

        $data = $body['data'] ?? [];

        return match (true) {
            ($data['discard_reason'] ?? null) !== null => 'descartado: '.$data['discard_reason'],
            ($data['applied_at'] ?? null) !== null => 'aplicado',
            default => 'registrado e enfileirado',
        };
    }
}
