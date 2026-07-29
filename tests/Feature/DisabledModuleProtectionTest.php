<?php

namespace Tests\Feature;

use App\Services\ModuleRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DisabledModuleProtectionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('modules.test_disabled', [
            'enabled' => false,
            'routes' => ['test-disabled-module.action'],
        ]);

        Route::match(
            ['GET', 'POST', 'PUT', 'DELETE'],
            '/_testing/disabled-module',
            fn () => response()->noContent()
        )->middleware('web')->name('test-disabled-module.action');
    }

    /**
     * @dataProvider disabledModuleRoutes
     */
    public function test_each_disabled_module_is_protected_by_the_central_registry(
        string $module,
        string $routeName
    ): void {
        $registry = app(ModuleRegistry::class);

        $this->assertFalse(config("modules.{$module}.enabled"));
        $this->assertFalse($registry->enabledForRoute($routeName));

        config()->set("modules.{$module}.enabled", true);

        $this->assertTrue($registry->enabledForRoute($routeName));

        config()->set("modules.{$module}.enabled", false);
    }

    public function test_disabled_module_returns_not_found_for_all_write_and_read_verbs(): void
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
            $this->call($method, '/_testing/disabled-module')->assertNotFound();
        }

        config()->set('modules.test_disabled.enabled', true);
        $this->get('/_testing/disabled-module')->assertNoContent();
    }

    public function test_disabled_post_does_not_change_historical_module_data(): void
    {
        config()->set('modules.appointments.enabled', false);

        $before = DB::table('appointments')->count();

        $this->post(route('appointments.store'))->assertNotFound();

        $this->assertSame($before, DB::table('appointments')->count());
    }

    public function test_public_and_enabled_routes_are_not_blocked(): void
    {
        $this->get(route('login'))->assertOk();
        $this->getJson('/api/health')->assertOk();
    }

    public static function disabledModuleRoutes(): array
    {
        return [
            'leads' => ['leads', 'leads.index'],
            'appointments' => ['appointments', 'appointments.index'],
            'work orders' => ['work_orders', 'work-orders.index'],
            'technicians' => ['technicians', 'employees.index'],
            'quality' => ['quality', 'vehicle-inspections.show'],
            'rework' => ['rework', 'rework-orders.index'],
            'delivery' => ['delivery', 'deliveries.show'],
            'warranties' => ['warranties', 'warranties.verify'],
            'advanced roll inventory' => ['advanced_roll_inventory', 'rolls.index'],
        ];
    }
}
