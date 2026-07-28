<?php

namespace App\Services;

class ProductionEnvironmentValidator
{
    public function errors(): array
    {
        $errors = [];
        $required = [
            'APP_KEY' => config('app.key'),
            'APP_URL' => config('app.url'),
            'DB_DATABASE' => config('database.connections.'.config('database.default').'.database'),
            'DB_USERNAME' => config('database.connections.'.config('database.default').'.username'),
            'MAIL_FROM_ADDRESS' => config('mail.from.address'),
        ];

        foreach ($required as $name => $value) {
            if (blank($value)) {
                $errors[] = "{$name} is required";
            }
        }

        if (config('app.env') !== 'production') {
            $errors[] = 'APP_ENV must be production';
        }
        if (config('app.debug')) {
            $errors[] = 'APP_DEBUG must be false';
        }
        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $errors[] = 'APP_URL must use HTTPS';
        }
        if (! config('security.force_https')) {
            $errors[] = 'FORCE_HTTPS must be true';
        }
        if (! config('session.secure')) {
            $errors[] = 'SESSION_SECURE_COOKIE must be true';
        }
        if (! config('session.http_only')) {
            $errors[] = 'SESSION_HTTP_ONLY must be true';
        }
        if (! in_array(config('session.same_site'), ['lax', 'strict'], true)) {
            $errors[] = 'SESSION_SAME_SITE must be lax or strict';
        }
        if (in_array('*', config('cors.allowed_origins', []), true)) {
            $errors[] = 'CORS_ALLOWED_ORIGINS must not contain a wildcard';
        }
        if (PHP_VERSION_ID < 80200 || PHP_VERSION_ID >= 80400) {
            $errors[] = 'PHP 8.2 or 8.3 is required; PHP 8.2 is recommended';
        }

        return $errors;
    }
}
