<?php

return [

    // Minutos que um pedido pendente segura a reserva antes de ser expirado pelo scheduler.
    'reservation_ttl' => (int) env('ORDER_RESERVATION_TTL', 15),

    // Minutos após o pagamento até a reconciliação redespachar efeitos pendentes. Maior
    // que o ciclo completo de retries do financeiro (~40 min), para não competir com ele.
    'reconcile_after' => (int) env('ORDER_RECONCILE_AFTER', 60),

    // Limite de ingressos por compra.
    'max_quantity_per_order' => (int) env('ORDER_MAX_QUANTITY', 10),

];
