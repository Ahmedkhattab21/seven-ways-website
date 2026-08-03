<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\UserDashboardProfileResolver;
use PHPUnit\Framework\TestCase;

class UserDashboardProfileResolverTest extends TestCase
{
    /**
     * @dataProvider profiles
     */
    public function test_it_resolves_the_landing_profile_in_the_shared_priority_order(
        array $roles,
        bool $dashboardPermission,
        string $profile,
        ?string $route
    ): void {
        $user = new class($roles, $dashboardPermission) extends User
        {
            public function __construct(private array $testRoles, private bool $dashboardPermission)
            {
                parent::__construct();
            }

            public function hasRole(string|array $roles): bool
            {
                return collect((array) $roles)->intersect($this->testRoles)->isNotEmpty();
            }

            public function hasPermission(string $permission): bool
            {
                return $permission === 'dashboard.view' && $this->dashboardPermission;
            }
        };
        $resolver = new UserDashboardProfileResolver;

        $this->assertSame($profile, $resolver->profile($user));
        $this->assertSame($route, $resolver->routeName($user));
    }

    public function profiles(): array
    {
        return [
            'system admin first' => [['system_admin', 'accountant'], true, 'system_admin', 'dashboards.executive'],
            'owner before accountant' => [['company_owner', 'accountant'], true, 'manager', 'dashboards.executive'],
            'general manager' => [['general_manager'], true, 'manager', 'dashboards.executive'],
            'accountant' => [['accountant'], true, 'accountant', 'accounting.dashboard'],
            'branch manager' => [['branch_manager'], true, 'branch_manager', 'dashboard'],
            'generic permitted' => [[], true, 'generic', 'dashboard'],
            'generic denied' => [[], false, 'generic', null],
        ];
    }

    /**
     * @dataProvider routeAccess
     */
    public function test_it_centralizes_dashboard_route_access(
        array $roles,
        array $permissions,
        string $route,
        bool $expected
    ): void {
        $user = new class($roles, $permissions) extends User
        {
            public function __construct(private array $testRoles, private array $testPermissions)
            {
                parent::__construct();
            }

            public function hasRole(string|array $roles): bool
            {
                return collect((array) $roles)->intersect($this->testRoles)->isNotEmpty();
            }

            public function hasPermission(string $permission): bool
            {
                return in_array($permission, $this->testPermissions, true);
            }

            public function isCompanyAdministrator(): bool
            {
                return $this->hasRole(['company_owner', 'general_manager']);
            }
        };

        $this->assertSame($expected, (new UserDashboardProfileResolver)->canAccessRoute($user, $route));
    }

    public function routeAccess(): array
    {
        return [
            'owner executive' => [['company_owner'], [], 'dashboards.executive', true],
            'general manager executive' => [['general_manager'], [], 'dashboards.executive', true],
            'system admin executive' => [['system_admin'], [], 'dashboards.executive', true],
            'explicit executive permission' => [[], ['dashboards.executive.view'], 'dashboards.executive', true],
            'branch manager executive denied' => [['branch_manager'], ['dashboard.view'], 'dashboards.executive', false],
            'accountant executive denied' => [['accountant'], ['accounting.accounts.view'], 'dashboards.executive', false],
            'dashboard permission' => [[], ['dashboard.view'], 'dashboard', true],
            'dashboard denied' => [[], [], 'dashboard', false],
        ];
    }
}
