<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],


    'allowed_origins' => [
        'https://www.grupoccpja.com.br',
        'https://grupoccpja.com.br',
        'https://ccpja.vercel.app',
        'http://localhost:3000',
    ],
    'allowed_origins_patterns' => [
        '#^https://.*\.vercel\.app$#',
        '#^https://(www\.)?grupoccpja\.com\.br$#',
    ],

    'allowed_headers' => ['Accept', 'Content-Type', 'Authorization'],

    'exposed_headers' => [],

    'max_age' => 0,


    'supports_credentials' => false,
];
