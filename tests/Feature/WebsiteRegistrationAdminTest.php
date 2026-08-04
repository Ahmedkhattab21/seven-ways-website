<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsiteRegistration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WebsiteRegistrationAdminTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_admin_can_see_registration_requests_in_sidebar_and_open_details(): void
    {
        $admin = $this->userWithRole('system_admin');
        $registration = WebsiteRegistration::query()->create($this->registrationData());

        $this->actingAs($admin)
            ->get(route('registration-requests.index'))
            ->assertOk()
            ->assertSee('الطلبات')
            ->assertSee('Ahmed Website')
            ->assertSee('href="'.route('registration-requests.index').'"', false);

        $this->actingAs($admin)
            ->get(route('registration-requests.show', $registration))
            ->assertOk()
            ->assertSee('Ahmed Website')
            ->assertSee('+201000000099');
    }

    public function test_non_admin_cannot_access_registration_requests(): void
    {
        $user = $this->userWithRole('company_owner');
        $registration = WebsiteRegistration::query()->create($this->registrationData());

        $this->actingAs($user)
            ->get(route('registration-requests.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('registration-requests.show', $registration))
            ->assertForbidden();
    }

    public function test_non_admin_does_not_see_registration_requests_in_sidebar(): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['name' => 'dashboard.view'],
            ['display_name' => 'Dashboard']
        );
        $role = Role::query()->create([
            'name' => 'dashboard_'.uniqid(),
            'display_name' => 'Dashboard',
            'scope' => 'system',
            'is_active' => true,
        ]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.route('registration-requests.index').'"', false);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('registration-requests.index'))
            ->assertRedirect(route('login'));
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            ['company_id' => null, 'name' => $roleName],
            [
                'display_name' => $roleName,
                'scope' => 'system',
                'is_system' => true,
                'is_active' => true,
            ]
        );
        $role->update(['is_active' => true]);

        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role);

        return $user;
    }

    private function registrationData(): array
    {
        return [
            'full_name' => 'Ahmed Website',
            'phone' => '+201000000099',
            'email' => 'website-request@example.com',
            'country' => 'egypt',
            'city' => 'Alexandria',
            'vehicle_type' => 'Mercedes',
            'vehicle_model' => 'G Class',
            'vehicle_year' => 2025,
            'service' => 'ppf',
            'preferred_branch' => 'alexandria',
            'notes' => 'Call after 6 PM.',
            'locale' => 'ar',
        ];
    }
}
