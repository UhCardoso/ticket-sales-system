<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Ingressos — pedido {{ $order->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; }
        .ticket { text-align: center; padding-top: 40px; page-break-after: always; }
        .ticket:last-child { page-break-after: auto; }
        h1 { font-size: 24px; margin-bottom: 4px; }
        .meta { font-size: 14px; color: #4b5563; margin: 2px 0; }
        .qr { margin: 32px 0 12px; width: 260px; height: 260px; }
        .code { font-family: DejaVu Sans Mono, monospace; font-size: 13px; }
    </style>
</head>
<body>
@foreach ($tickets as $ticket)
    <div class="ticket">
        <h1>{{ $order->ticketBatch->event->name }}</h1>
        <p class="meta">{{ \Illuminate\Support\Carbon::parse($order->ticketBatch->event->date_time)->format('d/m/Y H:i') }}</p>
        <p class="meta">{{ $order->ticketBatch->name }} — ingresso {{ $loop->iteration }} de {{ $loop->count }}</p>
        <p class="meta">{{ $order->buyer_name }}</p>

        <img class="qr" src="{{ $ticket['qr_code'] }}" alt="QR Code">
        <p class="code">{{ $ticket['code'] }}</p>
    </div>
@endforeach
</body>
</html>
