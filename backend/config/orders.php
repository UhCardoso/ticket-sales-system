<?php

return [

    // Minutos que um pedido pendente segura a reserva antes de ser expirado pelo scheduler.
    'reservation_ttl' => (int) env('ORDER_RESERVATION_TTL', 15),

    // Limite de ingressos por compra.
    'max_quantity_per_order' => (int) env('ORDER_MAX_QUANTITY', 10),

];
