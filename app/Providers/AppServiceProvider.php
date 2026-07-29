<?php

namespace App\Providers;

use App\Core\Tenancy\TenantContext;
use App\Services\SidebarNavigationService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext());
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer('partials.sidebar', function ($view) {
            $user = auth()->user();
            $view->with('sidebarNavigation', $user
                ? app(SidebarNavigationService::class)->for($user)
                : ['sections' => [], 'setup' => null, 'financialAlert' => null]);
        });
    }
}
