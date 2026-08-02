<?php

namespace Tests\Feature;

use App\Http\Middleware\ForceHttps;
use App\Services\ProductionEnvironmentValidator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PhaseTwentyProductionSecurityTest extends TestCase
{
    public function test_production_environment_example_has_safe_defaults_and_no_credentials(): void
    {
        $env = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('APP_ENV=production', $env);
        $this->assertStringContainsString('APP_DEBUG=false', $env);
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $env);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $env);
        $this->assertStringNotContainsString('DB_PORT=3307', $env);
        $this->assertStringNotContainsString('seven_ways_testing', $env);
        $this->assertStringNotContainsString('Test@123456', $env);
        $this->assertMatchesRegularExpression('/DB_PASSWORD=\R/', $env);
    }

    public function test_security_headers_are_applied_without_unsafe_eval(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringNotContainsString(
            "'unsafe-eval'",
            (string) $response->headers->get('Content-Security-Policy')
        );
        $this->assertStringContainsString(
            'frame-src https://www.google.com https://maps.google.com',
            (string) $response->headers->get('Content-Security-Policy')
        );
    }

    public function test_https_is_forced_only_for_production_http_requests(): void
    {
        $middleware = new ForceHttps;
        $next = fn () => new Response('ok');

        config(['app.env' => 'local', 'security.force_https' => true]);
        $this->assertSame(200, $middleware->handle(Request::create('/login'), $next)->getStatusCode());

        config(['app.env' => 'production', 'security.force_https' => true]);
        $response = $middleware->handle(Request::create('/login'), $next);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertStringStartsWith('https://', (string) $response->headers->get('Location'));
    }

    public function test_production_environment_validator_detects_unsafe_configuration(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => true,
            'app.url' => 'http://example.com',
            'security.force_https' => false,
            'session.secure' => false,
            'cors.allowed_origins' => ['*'],
        ]);

        $errors = app(ProductionEnvironmentValidator::class)->errors();

        $this->assertContains('APP_DEBUG must be false', $errors);
        $this->assertContains('APP_URL must use HTTPS', $errors);
        $this->assertContains('FORCE_HTTPS must be true', $errors);
        $this->assertContains('SESSION_SECURE_COOKIE must be true', $errors);
        $this->assertContains('CORS_ALLOWED_ORIGINS must not contain a wildcard', $errors);
    }

    public function test_error_page_exposes_only_the_correlation_reference(): void
    {
        request()->attributes->set('correlation_id', 'safe-reference');
        $html = view('errors.500')->render();

        $this->assertStringContainsString('safe-reference', $html);
        $this->assertStringNotContainsString(base_path(), $html);
        $this->assertStringNotContainsString('Stack trace', $html);
        $this->assertStringNotContainsString('SQLSTATE', $html);
    }
}
