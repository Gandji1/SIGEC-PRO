<?php

return [
    // Test database configuration
    'database' => [
        'default' => 'testing',
        'connections' => [
            'testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ],
    ],

    // Email configuration for testing
    'mail' => [
        'driver' => 'log',
    ],

    // Queue configuration for testing
    'queue' => [
        'default' => 'sync',
    ],

    // Cache configuration for testing
    'cache' => [
        'default' => 'array',
    ],

    // Session configuration for testing
    'session' => [
        'driver' => 'array',
    ],
];
