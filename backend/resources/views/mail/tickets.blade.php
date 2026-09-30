<x-mail::message>
# Seus ingressos

Olá, {{ $order->buyer_name }}. Seus {{ $order->quantity }} ingresso(s) para **{{ $order->ticketBatch->event->name }}** estão no PDF em anexo.

Cada ingresso tem um QR Code próprio, que será lido na entrada. Não compartilhe os códigos.

{{ config('app.name') }}
</x-mail::message>
