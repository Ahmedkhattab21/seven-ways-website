<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_health_endpoint_reports_application_and_database_status(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Application is healthy',
                'data' => [
                    'application' => 'ok',
                    'database' => 'ok',
                ],
                'errors' => null,
                'meta' => [],
            ]);
    }

    public function test_health_endpoint_failure_does_not_expose_internal_details(): void
    {
        \Illuminate\Support\Facades\DB::shouldReceive('select')
            ->once()
            ->andThrow(new RuntimeException('internal database failure detail'));

        $response = $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertExactJson([
                'success' => false,
                'message' => 'Application health check failed',
                'data' => null,
                'errors' => ['database' => ['unavailable']],
                'meta' => [
                    'application' => 'ok',
                    'database' => 'unavailable',
                ],
            ]);

        $this->assertStringNotContainsString(
            'internal database failure detail',
            $response->getContent()
        );
    }

    public function test_user_endpoint_rejects_unauthenticated_requests(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_authenticated_user_can_access_only_their_profile(): void
    {
        $user = User::factory()->make([
            'id' => 123,
            'name' => 'Authenticated User',
            'email' => 'authenticated@example.com',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJson([
                'id' => 123,
                'name' => 'Authenticated User',
                'email' => 'authenticated@example.com',
            ])
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('remember_token');
    }

    public function test_api_validation_errors_use_the_standard_envelope(): void
    {
        Route::post('/api/_foundation-test/validation', function () {
            request()->validate(['name' => ['required', 'string']]);
        });

        $this->postJson('/api/_foundation-test/validation')
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonPath('data', null)
            ->assertJsonStructure(['errors' => ['name'], 'meta']);
    }

    public function test_business_exceptions_use_the_standard_envelope(): void
    {
        Route::get('/api/_foundation-test/business-rule', function () {
            throw new BusinessRuleException(
                'Business rule failed',
                ['document' => ['cannot_be_modified']],
                409
            );
        });

        $this->getJson('/api/_foundation-test/business-rule')
            ->assertConflict()
            ->assertExactJson([
                'success' => false,
                'message' => 'Business rule failed',
                'data' => null,
                'errors' => ['document' => ['cannot_be_modified']],
                'meta' => [],
            ]);
    }

    public function test_unknown_api_routes_use_the_standard_envelope(): void
    {
        $this->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertExactJson([
                'success' => false,
                'message' => 'Resource not found',
                'data' => null,
                'errors' => [],
                'meta' => [],
            ]);
    }
}
