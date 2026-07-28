<?php

namespace App\Services;

use RuntimeException;

class UatEnvironmentGuard
{
    public const HOST = '127.0.0.1';

    public const PORT = 3307;

    public const DATABASE = 'seven_ways_uat';

    public const TEST_DATABASE = 'seven_ways_testing';

    public function assertSafe(): void
    {
        $environment = app()->environment();
        $connection = config('database.default');
        $database = config("database.connections.{$connection}");
        $expectedDatabase = $environment === 'testing' ? self::TEST_DATABASE : self::DATABASE;

        if (! in_array($environment, ['local', 'testing', 'uat', 'uat.local'], true)
            || $connection !== 'mysql'
            || ($database['host'] ?? null) !== self::HOST
            || (int) ($database['port'] ?? 0) !== self::PORT
            || ($database['database'] ?? null) !== $expectedDatabase) {
            throw new RuntimeException('STOP — Unsafe database target.');
        }
    }

    public function safeSummary(): array
    {
        $this->assertSafe();
        $connection = config('database.default');
        $database = config("database.connections.{$connection}");

        return [
            'APP_ENV' => app()->environment(),
            'DB_HOST' => $database['host'],
            'DB_PORT' => (string) $database['port'],
            'DB_DATABASE' => $database['database'],
            'PHP version' => PHP_VERSION,
        ];
    }
}
