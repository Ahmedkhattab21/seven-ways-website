<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationUiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_page_is_available_to_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('تسجيل الدخول إلى النظام')
            ->assertSee(asset(config('branding.logo')), false);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_dashboard_and_layout(): void
    {
        $user = User::factory()->create([
            'name' => 'مستخدم Seven Ways',
            'email' => 'operator+'.uniqid().'@example.com',
            'status' => 'active',
        ]);
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
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('لوحة التحكم')
            ->assertSee(asset(config('branding.logo')), false)
            ->assertSee('مستخدم Seven Ways')
            ->assertDontSee('فواتير المبيعات')
            ->assertDontSee('href="'.route('sales-invoices.index').'"', false)
            ->assertDontSee('المشتريات')
            ->assertDontSee('href="'.url('/purchases').'"', false);
    }

    public function test_authenticated_user_without_dashboard_permission_is_forbidden(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_logout_ends_the_authenticated_session(): void
    {
        $user = User::factory()->make();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_error_page_does_not_expose_sensitive_details(): void
    {
        config(['app.debug' => false]);

        Route::get('/_foundation-test/server-error', fn () => response()->view('errors.500', [], 500));

        $response = $this->get('/_foundation-test/server-error')
            ->assertStatus(500)
            ->assertSee('حدث خطأ غير متوقع')
            ->assertDontSee('Stack trace')
            ->assertDontSee('APP_KEY')
            ->assertDontSee('DB_PASSWORD');

        $this->assertStringNotContainsString(base_path(), $response->getContent());
    }
}
