<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\Lead;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Models\VehicleSize;
use App\Models\VehicleType;
use App\Policies\AttachmentPolicy;
use App\Policies\BranchPolicy;
use App\Policies\BranchSettingPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\LeadPolicy;
use App\Policies\ReferenceDataPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Policies\VehiclePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Company::class => CompanyPolicy::class,
        Branch::class => BranchPolicy::class,
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        BranchSetting::class => BranchSettingPolicy::class,
        Currency::class => ReferenceDataPolicy::class,
        Tax::class => ReferenceDataPolicy::class,
        Unit::class => ReferenceDataPolicy::class,
        PaymentMethod::class => ReferenceDataPolicy::class,
        VehicleBrand::class => ReferenceDataPolicy::class,
        VehicleModel::class => ReferenceDataPolicy::class,
        VehicleSize::class => ReferenceDataPolicy::class,
        VehicleType::class => ReferenceDataPolicy::class,
        FiscalYear::class => ReferenceDataPolicy::class,
        DocumentSequence::class => ReferenceDataPolicy::class,
        Customer::class => CustomerPolicy::class,
        Vehicle::class => VehiclePolicy::class,
        Lead::class => LeadPolicy::class,
        Attachment::class => AttachmentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::before(fn (User $user) => $user->hasRole('system_admin') ? true : null);
    }
}
