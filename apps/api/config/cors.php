<?php

$frontend = env('FRONTEND_URL', 'http://localhost:3000');
if (! is_string($frontend) || ! filter_var($frontend, FILTER_VALIDATE_URL)
    || ! in_array(parse_url($frontend, PHP_URL_SCHEME), ['http', 'https'], true)
    || parse_url($frontend, PHP_URL_USER) || parse_url($frontend, PHP_URL_PASS)
    || parse_url($frontend, PHP_URL_QUERY) || parse_url($frontend, PHP_URL_FRAGMENT)
    || ! in_array(parse_url($frontend, PHP_URL_PATH), [null, ''], true)) {
    throw new InvalidArgumentException('FRONTEND_URL must be an explicit HTTP(S) origin.');
}

return [
    'paths' => ['api/v1/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_origins' => [$frontend],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'X-XSRF-TOKEN', 'X-Requested-With'],
    'exposed_headers' => ['X-Request-ID', 'Retry-After'],
    'max_age' => 0,
    'supports_credentials' => true,
];
