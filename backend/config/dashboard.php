<?php

return [

    // Chave única do snapshot do painel no Redis. Um só snapshot serve todos os clientes.
    'cache_key' => 'dashboard.sales-summary',

    // Segundos que o snapshot do painel vive no cache. Curto de propósito: os ~30 clientes
    // em polling batem no cache, e não no MySQL que está sob contenção de escrita no pico.
    'cache_ttl' => (int) env('DASHBOARD_CACHE_TTL', 3),

];
