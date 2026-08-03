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
}
