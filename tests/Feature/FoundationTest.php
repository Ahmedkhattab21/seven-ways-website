<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\Route;
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
