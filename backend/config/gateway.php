<?php

return [

    /**
     * The URL to which the gateway will send notifications.
     */
    'notifications_url' => env('GATEWAY_NOTIFICATIONS_URL', env('APP_URL').'/api/gateway/notifications'),

    /**
     * The number of seconds the gateway will wait for a response before resending.
     */
    'response_timeout' => (int) env('GATEWAY_RESPONSE_TIMEOUT', 3),

];
