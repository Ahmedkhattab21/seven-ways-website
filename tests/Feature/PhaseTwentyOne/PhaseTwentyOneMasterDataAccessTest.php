<?php

namespace Tests\Feature\PhaseTwentyOne;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\SevenWaysUatSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesPhaseTwentyOneUat;
use Tests\TestCase;

class PhaseTwentyOneMasterDataAccessTest extends TestCase
{
    use DatabaseTransactions;
    use UsesPhaseTwentyOneUat;

    public function test_uat_seeder_is_idempotent_and_creates_reference_data_only(): void
    {
        $this->setUpUatContext();
        $protected = [
            'journal_entries', 'sales_invoices', 'supplier_invoices', 'stock_movements',
            'approval_tasks', 'system_notifications', 'treasury_transfers',
        ];
        $before = collect($protected)->mapWithKeys(fn (string $table) => [
            $table => DB::table($table)->where('company_id', $this->uatCompany->id)->count(),
        ]);

        app(SevenWaysUatSeeder::class)->run();

        $this->assertSame(1, Company::query()->where('name', 'Seven Ways UAT Egypt')->count());
        $this->assertSame(3, Branch::query()->where('company_id', $this->uatCompany->id)
            ->whereIn('code', ['UAT-CAI', 'UAT-GIZ', 'UAT-ALX'])->count());
        $this->assertSame(14, User::query()->where('company_id', $this->uatCompany->id)
            ->where('email', 'like', 'uat.%@sevenways.test')->count());
        $this->assertSame(5, Product::query()->where('company_id', $this->uatCompany->id)
            ->where('sku', 'like', 'UAT-%')->count());
        $this->assertSame(5, Service::query()->where('company_id', $this->uatCompany->id)
            ->where('code', 'like', 'UAT-%')->count());
        foreach ($protected as $table) {
            $this->assertSame($before[$table], DB::table($table)
                ->where('company_id', $this->uatCompany->id)->count(), $table);
        }
        $this->assertSame(0, JournalEntry::query()->where('company_id', $this->uatCompany->id)->count());
    }

    public function test_login_disabled_viewer_and_branch_scope_are_enforced(): void
    {
        $owner = $this->setUpUatContext();
        $this->post(route('logout'));
        $this->post(route('login'), [
            'email' => $owner->email,
            'password' => 'Uat@123456',
        ])->assertRedirect(route('dashboards.executive'));

        $this->post(route('logout'));
        $this->post(route('login'), [
            'email' => 'uat.disabled@sevenways.test',
            'password' => 'Uat@123456',
        ])->assertSessionHasErrors('email');

        $cairoManager = $this->uatUser('uat.cairo.manager@sevenways.test');
        $this->actingAs($cairoManager);
        app(TenantContext::class)->initialize($cairoManager);
        $this->assertTrue($cairoManager->canAccessBranch($this->uatBranches['UAT-CAI']));
        $this->assertFalse($cairoManager->canAccessBranch($this->uatBranches['UAT-GIZ']));
        $this->post(route('branch-context.store'), [
            'branch_id' => $this->uatBranches['UAT-GIZ']->id,
        ])->assertForbidden();

        $viewer = $this->uatUser('uat.viewer@sevenways.test');
        $this->assertFalse($viewer->roles()->where('roles.name', 'uat_viewer')
            ->firstOrFail()->permissions()
            ->where('permissions.name', '!=', 'dashboard.view')
            ->where('permissions.name', 'not like', '%.view')
            ->where('permissions.name', 'not like', '%.view_%')
            ->exists());
        $this->assertFalse(Role::query()->where('company_id', $this->uatCompany->id)
            ->where('name', 'accountant')->firstOrFail()
            ->permissions()->where('permissions.name', 'like', '%.approve')->exists());
    }
}
