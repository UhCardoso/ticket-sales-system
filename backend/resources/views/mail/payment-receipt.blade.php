<x-mail::message>
# Pagamento confirmado

Olá, {{ $order->buyer_name }}. Recebemos o pagamento do seu pedido.

<x-mail::table>
| | |
|:--|--:|
| Pedido | #{{ $order->id }} |
| Evento | {{ $order->ticketBatch->event->name }} |
| Lote | {{ $order->ticketBatch->name }} |
| Quantidade | {{ $order->quantity }} |
| Preço unitário | R$ {{ number_format((float) $order->unit_price, 2, ',', '.') }} |
| **Total pago** | **R$ {{ number_format((float) $order->total, 2, ',', '.') }}** |
| Data do pagamento | {{ $order->paid_at?->format('d/m/Y H:i') }} |
| Referência | {{ $order->payment_reference }} |
</x-mail::table>

Os ingressos chegam em um e-mail separado, com o PDF em anexo.

{{ config('app.name') }}
</x-mail::message>
