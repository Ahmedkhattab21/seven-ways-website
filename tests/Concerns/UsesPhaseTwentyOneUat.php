<?php

namespace Tests\Concerns;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\ProductionReferenceSeeder;
use Database\Seeders\SevenWaysUatSeeder;

trait UsesPhaseTwentyOneUat
{
    protected Company $uatCompany;

    protected array $uatBranches;

    protected function setUpUatContext(string $email = 'uat.owner@sevenways.test'): User
    {
        app(ProductionReferenceSeeder::class)->run();
        app(SevenWaysUatSeeder::class)->run();

        $this->uatCompany = Company::query()->where('name', 'Seven Ways UAT Egypt')->firstOrFail();
        $this->uatBranches = Branch::query()->where('company_id', $this->uatCompany->id)
            ->whereIn('code', ['UAT-CAI', 'UAT-GIZ', 'UAT-ALX'])->get()->keyBy('code')->all();
        $user = User::query()->where('company_id', $this->uatCompany->id)
            ->where('email', $email)->firstOrFail();

        $this->actingAs($user);
        session(['tenant.branch_id' => $user->branch_id]);
        app(TenantContext::class)->initialize($user);

        return $user;
    }

    protected function uatUser(string $email): User
    {
        return User::query()->where('company_id', $this->uatCompany->id)
            ->where('email', $email)->firstOrFail();
    }
}
