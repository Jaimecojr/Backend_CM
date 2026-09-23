<?php

return [

    'paths' => [
        'api/*',
        'login',
        'logout',
        'user',
        'sanctum/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
    ],
    
    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Content-Disposition: report exports set the download filename here
    // (see ReportExportController); browsers hide response headers from JS
    // across origins unless explicitly exposed, so the frontend can't read
    // the filename to name the downloaded file without this.
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 86400,

    'supports_credentials' => true,

];
