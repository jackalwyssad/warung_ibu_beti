<?php

return [
    'host'     => env('RABBITMQ_HOST', 'rabbitmq'),
    'port'     => (int) env('RABBITMQ_PORT', 5672),
    'user'     => env('RABBITMQ_USER', 'beti'),
    'password' => env('RABBITMQ_PASSWORD', 'rahasia123'),
    'vhost'    => env('RABBITMQ_VHOST', '/'),
    'exchange' => 'beti.pengadaan.events',
];
