<?php
return [
    'paths' => ['*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['http://localhost:3000', 'http://127.0.0.1:3000, https://ccpjaback.onrender.com'],
    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN', 'Accept', 'Authorization'],
    'allowed_origins_patterns' => [
        '^https://.*\.vercel\.app$',
    ],
    'exposed_headers' => ['Set-Cookie'],
    'max_age' => 0,
    'supports_credentials' => true,
];
