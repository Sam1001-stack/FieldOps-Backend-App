<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    'plans' => [
        'trial' => [
            'name' => 'Trial',
            'price_cents' => 0,
            'interval' => 'month',
            'users' => 8,
            'jobs' => 50,
            'storage_mb' => 1024,
        ],
        'starter' => [
            'name' => 'Starter',
            'price_cents' => 4900,
            'interval' => 'month',
            'users' => 5,
            'jobs' => 80,
            'storage_mb' => 2048,
        ],
        'team' => [
            'name' => 'Team',
            'price_cents' => 14900,
            'interval' => 'month',
            'users' => 25,
            'jobs' => 500,
            'storage_mb' => 10240,
        ],
    ],
];
