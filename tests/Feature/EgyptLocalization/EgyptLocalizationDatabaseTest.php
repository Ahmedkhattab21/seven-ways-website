<?php

namespace Tests\Feature\EgyptLocalization;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\OpeningBalanceDocument;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Tax;
use App\Services\CompanySettingsService;
use App\Services\FinancialHistoryInspector;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\TreasuryManualQaSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\Concerns\BuildsTreasuryOperationsContext;
use Tests\TestCase;

class EgyptLocalizationDatabaseTest extends TestCase
{
    use BuildsTreasuryOperationsContext, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(ReferenceDataSeeder::class)->run();
    }

    public function test_reference_data_is_idempotent_and_keeps_sar_as_an_additional_currency(): void
    {
        app(ReferenceDataSeeder::class)->run();

        $this->assertSame(1, Currency::query()->where('code', 'EGP')->where('is_active', true)->count());
        $this->assertSame(1, Currency::query()->where('code', 'SAR')->where('is_active', true)->count());
        $this->assertSame('ج.م', Currency::query()->where('code', 'EGP')->value('symbol'));
    }

    public function test_new_company_receives_egypt_defaults_and_configurable_vat_reference(): void
    {
        $company = Company::query()->forceCreate([
            'name' => 'Egypt QA '.uniqid(),
            'is_active' => true,
        ])->fresh('currency');

        $this->assertSame('EG', $company->country_code);
        $this->assertSame('EGP', $company->currency->code);
        $this->assertSame('Africa/Cairo', $company->timezone);

        $history = $this->historySummary(0);
        $this->app->instance(FinancialHistoryInspector::class, $history);
        Artisan::call('localization:audit-egypt', ['--apply-safe-defaults' => true]);

        $vat = Tax::query()->where('company_id', $company->id)->where('code', 'VAT14-EG')->firstOrFail();
        $this->assertSame('14.0000', $vat->rate);
        Tax::query()->forceCreate([
            'company_id' => $company->id, 'code' => 'ZERO-'.uniqid(),
            'name' => 'Zero rated', 'rate' => 0, 'tax_type' => 'both', 'is_active' => true,
        ]);
        $this->assertDatabaseHas('taxes', ['company_id' => $company->id, 'rate' => 0]);
    }

    public function test_audit_is_read_only_and_posted_history_blocks_safe_defaults(): void
    {
        $sar = Currency::query()->where('code', 'SAR')->firstOrFail();
        $company = Company::query()->forceCreate([
            'name' => 'Historical SAR QA '.uniqid(),
            'country_code' => 'SA',
            'currency_code' => 'SAR',
            'currency_id' => $sar->id,
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->app->instance(FinancialHistoryInspector::class, $this->historySummary(0));
        Artisan::call('localization:audit-egypt');
        $this->assertSame('SAR', $company->fresh()->currency_code);

        $history = $this->historySummary(1);
        $this->app->instance(FinancialHistoryInspector::class, $history);
        Artisan::call('localization:audit-egypt', ['--apply-safe-defaults' => true]);
        $this->assertSame('SAR', $company->fresh()->currency_code);

        $egp = Currency::query()->where('code', 'EGP')->firstOrFail();
        $service = new CompanySettingsService($history);
        try {
            $service->update($company, ['currency_id' => $egp->id, 'currency_code' => 'EGP']);
            $this->fail('Historical base currency was changed.');
        } catch (ValidationException) {
            $this->assertSame('SAR', $company->fresh()->currency_code);
        }
    }

    public function test_treasury_qa_seeder_never_falls_back_when_egp_is_unavailable(): void
    {
        Currency::query()->where('code', 'EGP')->update(['is_active' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Active EGP currency is required');
        app(TreasuryManualQaSeeder::class)->run();
    }

    public function test_history_inspector_blocks_posted_sources_but_ignores_drafts_and_other_companies(): void
    {
        $context = $this->treasuryContext();
        $company = $context['company'];
        $common = [
            'company_id' => $company->id,
            'branch_id' => $context['branch']->id,
            'currency_id' => $context['currency']->id,
            'created_by' => $context['user']->id,
        ];

        JournalEntry::factory()->create($common + [
            'fiscal_year_id' => $context['year']->id,
            'accounting_period_id' => $context['period']->id,
            'status' => 'posted',
        ]);
        JournalEntry::factory()->create($common + [
            'fiscal_year_id' => $context['year']->id,
            'accounting_period_id' => $context['period']->id,
            'status' => 'draft',
        ]);

        $customer = Customer::factory()->create([
            'company_id' => $company->id,
            'created_branch_id' => $context['branch']->id,
            'assigned_branch_id' => $context['branch']->id,
        ]);
        SalesInvoice::factory()->create($common + [
            'customer_id' => $customer->id,
            'status' => 'issued',
        ]);
        SalesInvoice::factory()->create($common + [
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);

        $supplier = Supplier::factory()->create([
            'company_id' => $company->id,
            'created_by' => $context['user']->id,
        ]);
        SupplierInvoice::factory()->create($common + [
            'supplier_id' => $supplier->id,
            'status' => 'posted',
        ]);
        SupplierInvoice::factory()->create($common + [
            'supplier_id' => $supplier->id,
            'status' => 'draft',
        ]);

        OpeningBalanceDocument::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $context['branch']->id,
            'fiscal_year_id' => $context['year']->id,
            'created_by' => $context['user']->id,
            'status' => 'posted',
        ]);
        OpeningBalanceDocument::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $context['branch']->id,
            'fiscal_year_id' => $context['year']->id,
            'created_by' => $context['user']->id,
            'status' => 'draft',
        ]);

        $inspector = app(FinancialHistoryInspector::class);
        $summary = $inspector->summary($company->fresh('currency'));
        $this->assertSame(4, $summary['posted_records']);
        $this->assertSame(1, $summary['opening_balances']);
        $this->assertTrue($inspector->hasPostedFinancialMovements($company));

        $otherCompany = Company::query()->create([
            'name' => 'No history '.uniqid(),
            'currency_id' => $context['currency']->id,
        ]);
        $this->assertSame(0, $inspector->summary($otherCompany->fresh('currency'))['posted_records']);
        $this->assertFalse($inspector->hasPostedFinancialMovements($otherCompany));
    }

    private function historySummary(int $postedRecords): FinancialHistoryInspector
    {
        $history = Mockery::mock(FinancialHistoryInspector::class);
        $history->shouldReceive('summary')->andReturn([
            'posted_records' => $postedRecords,
            'sar_documents' => $postedRecords,
            'sar_journals' => 0,
            'opening_balances' => 0,
            'vat_15_lines' => 0,
            'first_movement_date' => null,
            'last_movement_date' => null,
            'currency_usage' => $postedRecords ? ['SAR' => $postedRecords] : [],
        ]);
        $history->shouldReceive('hasPostedFinancialMovements')->andReturn($postedRecords > 0);

        return $history;
    }
}
