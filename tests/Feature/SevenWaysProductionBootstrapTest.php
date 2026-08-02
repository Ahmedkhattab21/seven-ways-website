<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashBox;
use App\Models\CashBoxSession;
use App\Models\Company;
use App\Models\Currency;
use App\Models\DocumentSequence;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DocumentNumberService;
use App\Services\ProductionBootstrap\SevenWaysProductionBootstrap;
use App\Support\DocumentSequenceCatalog;
use Database\Seeders\AccountingFoundationSeeder;
use Database\Seeders\FoundationPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SevenWaysProductionBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FoundationPermissionSeeder::class);
        $currency = Currency::query()->create([
            'code' => 'EGP', 'name_ar' => 'جنيه مصري', 'name_en' => 'Egyptian Pound',
            'symbol' => 'EGP', 'decimal_places' => 2, 'is_active' => true,
        ]);
        $this->company = Company::query()->create([
            'name' => 'Seven Ways', 'country_code' => 'EG', 'currency_code' => 'EGP',
            'currency_id' => $currency->id, 'timezone' => 'Africa/Cairo', 'is_active' => true,
        ]);
        $owner = User::query()->forceCreate([
            'name' => 'Bootstrap Actor', 'email' => 'bootstrap.actor@example.com',
            'password' => Hash::make('ActorPassword!123'), 'company_id' => $this->company->id,
            'status' => 'active',
        ]);
        $owner->roles()->syncWithoutDetaching([
            Role::query()->whereNull('company_id')->where('name', 'company_owner')->value('id'),
        ]);
        $this->seed(AccountingFoundationSeeder::class);
        config([
            'sevenways_production.company_id' => $this->company->id,
            'sevenways_production.users' => [
                'nasr_manager' => $this->user('nasr.manager@example.com', 'branch_manager', 'CAI-MAIN', 'مسؤول فرع مدينة نصر'),
                'alex_manager' => $this->user('alex.manager@example.com', 'branch_manager', 'ALEX', 'مسؤول فرع الإسكندرية'),
                'accountant' => $this->user('accountant@example.com', 'accountant', 'CAI-MAIN', 'محاسب Seven Ways'),
                'general_manager' => $this->user('general.manager@example.com', 'general_manager', 'CAI-MAIN', 'المدير العام Seven Ways'),
            ],
        ]);
    }

    public function test_bootstrap_is_idempotent_and_preserves_sequences_and_operational_tables(): void
    {
        $bootstrap = app(SevenWaysProductionBootstrap::class);
        $first = $bootstrap->configure()->runAll();
        $sequence = DocumentSequence::query()->where('document_type', 'sales_invoice')->firstOrFail();
        $sequence->forceFill(['current_number' => 37])->save();
        $password = User::query()->where('email', 'nasr.manager@example.com')->value('password');

        $second = $bootstrap->configure()->runAll();

        $this->assertContains($first['status'], ['READY', 'READY WITH WARNINGS']);
        $this->assertContains($second['status'], ['READY', 'READY WITH WARNINGS']);
        $this->assertSame(1, Branch::query()->where('company_id', $this->company->id)->where('code', 'CAI-MAIN')->count());
        $this->assertSame(1, Branch::query()->where('company_id', $this->company->id)->where('code', 'ALEX')->count());
        $this->assertSame(1, Branch::query()->where('company_id', $this->company->id)->where('is_main', true)->count());
        $this->assertSame(37, $sequence->fresh()->current_number);
        $this->assertSame($password, User::query()->where('email', 'nasr.manager@example.com')->value('password'));
        $this->assertSame(0, CashBoxSession::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_users_have_expected_branch_access_and_responsible_managers(): void
    {
        app(SevenWaysProductionBootstrap::class)->configure()->runAll();

        $nasr = User::query()->where('email', 'nasr.manager@example.com')->firstOrFail();
        $alex = User::query()->where('email', 'alex.manager@example.com')->firstOrFail();
        $accountant = User::query()->where('email', 'accountant@example.com')->firstOrFail();
        $manager = User::query()->where('email', 'general.manager@example.com')->firstOrFail();
        $this->assertSame(['CAI-MAIN'], $nasr->accessibleBranches()->pluck('code')->all());
        $this->assertSame(['ALEX'], $alex->accessibleBranches()->pluck('code')->all());
        $this->assertEqualsCanonicalizing(['CAI-MAIN', 'ALEX'], $accountant->accessibleBranches()->pluck('code')->all());
        $this->assertEqualsCanonicalizing(['CAI-MAIN', 'ALEX'], $manager->accessibleBranches()->pluck('code')->all());
        $this->assertSame($nasr->id, Branch::query()->where('code', 'CAI-MAIN')->value('responsible_user_id'));
        $this->assertSame($alex->id, Branch::query()->where('code', 'ALEX')->value('responsible_user_id'));
    }

    public function test_operating_data_catalog_and_complete_sequences_are_created_without_fake_products(): void
    {
        app(SevenWaysProductionBootstrap::class)->configure()->runAll();

        $this->assertSame(2, Warehouse::query()->where('company_id', $this->company->id)
            ->where('is_main', true)->where('allows_sale_issue', true)->count());
        $this->assertSame(2, CashBox::query()->where('company_id', $this->company->id)
            ->where('is_primary', true)->where('status', 'active')->count());
        $this->assertSame(4, ProductCategory::query()->where('company_id', $this->company->id)->count());
        $this->assertSame(14, ProductBrand::query()->where('company_id', $this->company->id)->count());
        $this->assertSame(1, ProductBrand::query()->where('company_id', $this->company->id)->where('code', '3M')->count());
        $this->assertDatabaseCount('products', 0);
        $this->assertSame(
            count(DocumentSequenceCatalog::production()) * 2,
            DocumentSequence::query()->where('company_id', $this->company->id)
                ->whereIn('branch_id', Branch::query()->where('company_id', $this->company->id)->pluck('id'))->count()
        );
    }

    public function test_dry_run_command_changes_no_rows_and_never_prints_passwords(): void
    {
        $before = [
            'branches' => Branch::query()->count(),
            'users' => User::query()->count(),
            'sequences' => DocumentSequence::query()->count(),
        ];

        $this->artisan('sevenways:bootstrap-production', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();

        $this->assertSame($before['branches'], Branch::query()->count());
        $this->assertSame($before['users'], User::query()->count());
        $this->assertSame($before['sequences'], DocumentSequence::query()->count());
        $reports = glob(storage_path('app/private/production-bootstrap-reports/*.md')) ?: [];
        $report = (string) file_get_contents(end($reports));
        $this->assertStringNotContainsString('ProductionPassword!123', $report);
        $this->assertStringNotContainsString('$2y$', $report);
    }

    public function test_document_sequence_catalog_covers_every_configured_runtime_type(): void
    {
        $configured = DocumentSequenceCatalog::codeConfiguredTypes();
        foreach (DocumentSequenceCatalog::production() as $type => $definition) {
            $this->assertContains($type, $configured, "Missing configured document type {$type}");
            $this->assertNotEmpty($definition['short_code']);
            $this->assertContains($definition['reset_period'], ['never', 'yearly']);
        }
        $this->assertSame(
            DocumentNumberService::scopeKey(1, 2, 'sales_invoice', '2026'),
            '1:2:sales_invoice:2026'
        );
    }

    public function test_verification_is_read_only_after_successful_bootstrap(): void
    {
        $bootstrap = app(SevenWaysProductionBootstrap::class);
        $bootstrap->configure()->runAll();
        $counts = [
            'branches' => Branch::query()->count(),
            'users' => User::query()->count(),
            'sequences' => DocumentSequence::query()->count(),
        ];

        $result = $bootstrap->configure()->verify();

        $this->assertSame('READY', $result['status'], implode(PHP_EOL, $result['issues']));
        $this->assertSame($counts['branches'], Branch::query()->count());
        $this->assertSame($counts['users'], User::query()->count());
        $this->assertSame($counts['sequences'], DocumentSequence::query()->count());
    }

    public function test_apply_command_is_blocked_outside_production(): void
    {
        $this->artisan('sevenways:bootstrap-production', ['--apply' => true, '--force' => true])
            ->expectsOutputToContain('Apply requires APP_ENV=production')
            ->assertFailed();
    }

    private function user(string $email, string $role, string $branch, string $name): array
    {
        return [
            'name' => $name, 'email' => $email, 'password' => 'TestingOnlyPassword!123',
            'role' => $role, 'branch' => $branch,
        ];
    }
}
