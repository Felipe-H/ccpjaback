<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],


    'allowed_origins' => [
        'http://localhost:3000',
        'https://ccpja.vercel.app',
    ],

    'allowed_origins_patterns' => [
        '#^https://.*\.vercel\.app$#',
    ],

    'allowed_headers' => ['Accept', 'Content-Type', 'Authorization'],

    'exposed_headers' => [],

    'max_age' => 0,


    'supports_credentials' => false,
];
