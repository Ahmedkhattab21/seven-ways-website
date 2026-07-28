<?php

return [
    'force_https' => (bool) env('FORCE_HTTPS', false),
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', ''))
    ))),
    'content_security_policy' => env(
        'CONTENT_SECURITY_POLICY',
        "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; ".
        "script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; ".
        "font-src 'self' data:; media-src 'self'; connect-src 'self'"
    ),
    'permissions_policy' => env(
        'PERMISSIONS_POLICY',
        'camera=(), microphone=(), geolocation=(), payment=()'
    ),
];
