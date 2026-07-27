<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Currency;
use App\Models\CustomerSource;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\PaymentMethod;
use App\Models\Tax;
use App\Services\DocumentNumberService;
use App\Services\FinancialHistoryInspector;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use RuntimeException;

class SevenWaysOperationalSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('SevenWaysOperationalSeeder is restricted to local and testing environments.');
        }

        $company = Company::query()->where('name', 'Seven Ways')->firstOrFail();
        $egp = Currency::query()->where('code', 'EGP')->where('is_active', true)->first();
        if (! $egp) {
            throw new RuntimeException('Active EGP currency is required. Run ReferenceDataSeeder first.');
        }
        $hasHistory = app(FinancialHistoryInspector::class)->hasPostedFinancialMovements($company);
        if (! $hasHistory) {
            $company->forceFill([
                'country_code' => 'EG', 'currency_id' => $egp->id,
                'currency_code' => 'EGP', 'timezone' => 'Africa/Cairo',
            ])->save();
        }

        foreach ([
            'walk_in' => 'زيارة مباشرة', 'google' => 'Google', 'instagram' => 'Instagram',
            'snapchat' => 'Snapchat', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp',
            'referral' => 'ترشيح', 'car_showroom' => 'معرض سيارات',
            'sales_representative' => 'مندوب مبيعات', 'other' => 'أخرى',
        ] as $code => $name) {
            CustomerSource::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'is_active' => true]
            );
        }

        $vat = Tax::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'VAT14-EG'],
            [
                'name' => 'ضريبة القيمة المضافة المصرية 14%', 'rate' => 14, 'tax_type' => 'both',
                'is_default' => ! $hasHistory, 'is_inclusive' => false, 'is_active' => true,
            ]
        );
        if (! $hasHistory) {
            Tax::query()->where('company_id', $company->id)->whereKeyNot($vat->id)
                ->update(['is_default' => false]);
            $company->forceFill(['default_tax_id' => $vat->id])->save();
        }

        foreach ([
            ['CASH', 'نقدي', 'cash', false, true],
            ['CARD', 'شبكة', 'card', true, false],
            ['BANK', 'تحويل بنكي', 'bank_transfer', true, false],
            ['ONLINE', 'دفع إلكتروني', 'online', true, false],
            ['CREDIT', 'آجل', 'credit', false, false],
        ] as $order => [$code, $name, $type, $reference, $cash]) {
            PaymentMethod::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name' => $name, 'type' => $type, 'requires_reference' => $reference,
                    'is_cash' => $cash, 'is_active' => true, 'sort_order' => $order + 1,
                ]
            );
        }

        $start = Carbon::today()->month($company->fiscal_year_start_month)->startOfMonth();
        if ($start->isFuture()) {
            $start->subYear();
        }
        $end = $start->copy()->addYear()->subDay();
        FiscalYear::query()->updateOrCreate(
            ['company_id' => $company->id, 'start_date' => $start->toDateString()],
            [
                'name' => $start->format('Y').'/'.$end->format('Y'), 'end_date' => $end->toDateString(),
                'status' => 'open', 'is_current' => true,
            ]
        );

        $types = [
            'quotation' => 'QUO', 'appointment' => 'APT', 'work_order' => 'WO',
            'sales_invoice' => 'INV', 'purchase_request' => 'PR', 'purchase_order' => 'PO',
            'goods_receipt' => 'GRN', 'purchase_invoice' => 'PINV', 'stock_transfer' => 'ST',
            'receipt_voucher' => 'RV', 'payment_voucher' => 'PV', 'warranty' => 'WAR',
            'warranty_claim' => 'WCL', 'journal_entry' => 'JE', 'expense' => 'EXP',
        ];
        $periodKey = now()->format('Y');
        foreach ($company->branches()->where('is_active', true)->get() as $branch) {
            foreach ($types as $type => $shortCode) {
                DocumentSequence::query()->updateOrCreate(
                    ['scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, $type, $periodKey)],
                    [
                        'company_id' => $company->id, 'branch_id' => $branch->id,
                        'document_type' => $type, 'prefix' => "{BRANCH}-{$shortCode}-{YYYY}-",
                        'current_number' => 0, 'padding' => 6, 'reset_period' => 'yearly',
                        'period_key' => $periodKey, 'is_active' => true,
                    ]
                );
            }
            foreach (['customer' => ['CUS-', 'never', null], 'lead' => ['{BRANCH}-LEAD-{YYYY}-', 'yearly', $periodKey]] as $type => [$prefix, $reset, $key]) {
                DocumentSequence::query()->updateOrCreate(
                    ['scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, $type, $key)],
                    [
                        'company_id' => $company->id, 'branch_id' => $branch->id,
                        'document_type' => $type, 'prefix' => $prefix, 'current_number' => 0,
                        'padding' => 6, 'reset_period' => $reset, 'period_key' => $key, 'is_active' => true,
                    ]
                );
            }
        }

        $defaultPayment = PaymentMethod::query()->where('company_id', $company->id)->where('code', 'CASH')->first();
        foreach ($company->branches as $branch) {
            $settings = $branch->settings()->firstOrNew();
            $settings->default_payment_method_id = $defaultPayment?->id;
            if (! $hasHistory) {
                $settings->default_tax_id = $vat->id;
            }
            $settings->save();
        }
    }
}
