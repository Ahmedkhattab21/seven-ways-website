<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardTestingDatabase();
    }

    protected function fakeImage(string $name = 'image.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQmcAAAAASUVORK5CYII=', true)
        );
    }

    private function guardTestingDatabase(): void
    {
        $testDatabase = (string) config('database.connections.mysql.database');
        $developmentEnvironment = parse_ini_file(base_path('.env'), false, INI_SCANNER_RAW) ?: [];
        $developmentDatabase = trim((string) ($developmentEnvironment['DB_DATABASE'] ?? ''), "\"'");

        if (! app()->environment('testing')) {
            throw new RuntimeException('Database tests may only run in the testing environment.');
        }

        if (config('database.default') !== 'mysql') {
            throw new RuntimeException('Database tests must use MySQL.');
        }

        if ($testDatabase === '' || $testDatabase === $developmentDatabase) {
            throw new RuntimeException('The testing database must be separate from the development database.');
        }

        if (! preg_match('/_(test|testing)(?:_|$)/i', $testDatabase)) {
            throw new RuntimeException('The testing database name must contain _test or _testing.');
        }
    }
}
