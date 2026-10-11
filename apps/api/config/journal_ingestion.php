<?php

return [
    'crossref' => [
        'base_url' => env('CROSSREF_API_BASE_URL', 'https://api.crossref.org'),
        'mailto' => env('CROSSREF_API_MAILTO', ''),
        'timeout' => env('CROSSREF_API_TIMEOUT', 20),
        'connect_timeout' => env('CROSSREF_API_CONNECT_TIMEOUT', 5),
    ],
    'doaj_csv' => [
        'timeout' => env('DOAJ_CSV_TIMEOUT', 60),
        'connect_timeout' => env('DOAJ_CSV_CONNECT_TIMEOUT', 5),
        'max_download_bytes' => env('DOAJ_CSV_MAX_DOWNLOAD_BYTES', 33554432),
    ],
    'doaj' => [
        // Only official DOAJ HTTPS API roots are accepted by the client.
        'base_url' => env('DOAJ_API_BASE_URL', 'https://doaj.org/api/v4'),
        'timeout' => env('DOAJ_API_TIMEOUT', 20),
        'connect_timeout' => env('DOAJ_API_CONNECT_TIMEOUT', 5),
        'attempts' => env('DOAJ_API_ATTEMPTS', 3),
        'delay_ms' => env('DOAJ_API_DELAY_MS', 1000),
        'page_size' => env('DOAJ_API_PAGE_SIZE', 10),
        'max_response_bytes' => env('DOAJ_API_MAX_RESPONSE_BYTES', 1048576),
        'user_agent' => env('DOAJ_API_USER_AGENT', 'Fikarohana/0.1 (controlled journal ingestion)'),
    ],
];
