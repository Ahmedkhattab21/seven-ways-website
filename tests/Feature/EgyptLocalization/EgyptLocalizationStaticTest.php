<?php

namespace Tests\Feature\EgyptLocalization;

use App\Models\Company;
use App\Models\Currency;
use App\Services\MoneyFormatter;
use App\Services\PhoneNormalizer;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EgyptLocalizationStaticTest extends TestCase
{
    public function test_egypt_defaults_money_formatting_and_phone_normalization(): void
    {
        $this->assertSame('EG', config('localization.default_country_code'));
        $this->assertSame('EGP', config('localization.default_currency_code'));
        $this->assertSame('Africa/Cairo', config('localization.default_timezone'));

        $currency = new Currency([
            'code' => 'EGP', 'name_ar' => 'جنيه مصري', 'name_en' => 'Egyptian Pound',
            'symbol' => 'ج.م', 'decimal_places' => 2, 'is_active' => true,
        ]);
        $this->assertSame('1,250.00 ج.م', app(MoneyFormatter::class)->format('1250', $currency, 'ar'));
        $this->assertSame('1,250.00 EGP', app(MoneyFormatter::class)->format('1250', $currency, 'en'));

        $sar = new Currency(['code' => 'SAR', 'symbol' => 'ر.س', 'decimal_places' => 2]);
        $usd = new Currency(['code' => 'USD', 'symbol' => '$', 'decimal_places' => 3]);
        $company = new Company(['money_decimal_places' => 2]);
        $company->setRelation('currency', $currency);
        $document = new \stdClass;
        $document->currency = $usd;
        $formatter = app(MoneyFormatter::class);
        $this->assertSame('10.000 USD', $formatter->formatDocument(10, $document, $company, 'en'));
        $this->assertSame('10.00 SAR', $formatter->format(10, $sar, 'en'));
        $this->assertSame('10.00 EGP', $formatter->format(10, null, 'en', $company));
        $this->assertSame('10.00 EGP', $formatter->format(10, null, 'en'));

        $phones = app(PhoneNormalizer::class);
        $this->assertSame('201000000000', $phones->normalize('01000000000', 'EG'));
        $this->assertSame('201000000000', $phones->normalize('+20 10 0000 0000', 'EG'));
        $this->assertSame('20212345678', $phones->normalize('02 1234 5678', 'EG'));
        $this->assertSame('442079460958', $phones->normalize('+44 20 7946 0958', 'GB'));
    }

    public function test_closure_integrity_and_production_apply_guard(): void
    {
        $originalCompanyMigration = file_get_contents(
            database_path('migrations/2026_07_25_100000_create_companies_table.php')
        );
        $operationalMigration = file_get_contents(
            database_path('migrations/2026_07_25_110100_add_operational_settings_to_companies.php')
        );
        $egyptMigration = file_get_contents(
            database_path('migrations/2026_07_27_000000_set_egypt_company_column_defaults.php')
        );

        $this->assertStringContainsString("default('SA')", $originalCompanyMigration);
        $this->assertStringContainsString("default('SAR')", $originalCompanyMigration);
        $this->assertStringContainsString("default('Asia/Riyadh')", $originalCompanyMigration);
        $this->assertStringContainsString("company->currency_code ?: 'SAR'", $operationalMigration);
        $this->assertStringContainsString('ALTER TABLE companies ALTER COLUMN', $egyptMigration);
        foreach (['DB::table(', 'update(', 'delete(', 'drop', 'rename'] as $unsafeOperation) {
            $this->assertStringNotContainsString($unsafeOperation, $egyptMigration);
        }

        $this->assertSame('EG', config('website.defaults.country_code'));
        $this->assertSame('EGP', config('website.defaults.currency_code'));
        $this->assertSame('Africa/Cairo', config('website.defaults.timezone'));
        $this->assertSame('ar_EG', config('website.defaults.locale'));
        $footer = file_get_contents(resource_path('views/website/partials/footer.blade.php'));
        $this->assertStringContainsString('value="egypt" data-sw-footer-country checked', $footer);
        $this->assertStringContainsString('footer_socials.egypt', $footer);

        $foundation = file_get_contents(database_path('seeders/TreasuryFoundationSeeder.php'));
        foreach (['Saudi National Bank', 'Al Rajhi Bank', 'Riyad Bank', 'Saudi Awwal Bank'] as $bank) {
            $this->assertStringNotContainsString($bank, $foundation);
        }
        $this->assertStringContainsString("'scope_key' => 'system:OTHER'", $foundation);

        $currentEnvironment = $this->app['env'];
        try {
            $this->app['env'] = 'production';
            $this->assertSame(1, Artisan::call('localization:audit-egypt', [
                '--apply-safe-defaults' => true,
            ]));
        } finally {
            $this->app['env'] = $currentEnvironment;
        }
    }

    public function test_treasury_qa_sources_are_egyptian_and_require_egp(): void
    {
        $seeder = file_get_contents(database_path('seeders/TreasuryManualQaSeeder.php'));
        foreach (['QA-RUH', 'QA-DMM', 'Riyadh', 'Dammam', 'الرياض', 'الدمام'] as $oldValue) {
            $this->assertStringNotContainsString($oldValue, $seeder);
        }
        foreach (['QA-CAI', 'QA-GIZ', 'qa.cairo.cashier@sevenways.test', 'qa.giza.cashier@sevenways.test'] as $value) {
            $this->assertStringContainsString($value, $seeder);
        }
        $this->assertStringContainsString("where('code', 'EGP')->where('is_active', true)", $seeder);
        $this->assertStringContainsString('SAR fallback is not allowed', $seeder);

        foreach ([
            base_path('docs/testing/phase-15c-treasury-manual-cycle.md'),
            base_path('docs/testing/phase-15c-treasury-qa-report.md'),
        ] as $path) {
            $contents = file_get_contents($path);
            foreach (['SAR', 'Riyadh', 'Dammam', 'QA-RUH', 'QA-DMM', 'الرياض', 'الدمام', 'ريال', 'ر.س', '+966', 'Asia/Riyadh'] as $oldValue) {
                $this->assertStringNotContainsString($oldValue, $contents);
            }
        }
    }

    public function test_tax_defaults_come_from_company_settings_not_fixed_blade_values(): void
    {
        foreach ([
            resource_path('views/purchase-orders/form.blade.php'),
            resource_path('views/supplier-invoices/form.blade.php'),
            resource_path('views/supplier-credit-notes/form.blade.php'),
        ] as $path) {
            $contents = file_get_contents($path);
            $this->assertStringNotContainsString('value="15"', $contents);
            $this->assertStringContainsString('defaultTax?->rate ?? 0', $contents);
        }

        $salesService = file_get_contents(app_path('Services/SalesInvoiceService.php'));
        $this->assertStringContainsString('$tax?->rate', $salesService);
        $this->assertStringNotContainsString('0.15', $salesService);
    }
}
