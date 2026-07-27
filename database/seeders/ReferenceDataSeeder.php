<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Unit;
use App\Models\VehicleSize;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['EGP', 'جنيه مصري', 'Egyptian Pound', 'ج.م', 2],
            ['SAR', 'ريال سعودي', 'Saudi Riyal', 'ر.س', 2],
            ['USD', 'دولار أمريكي', 'US Dollar', '$', 2],
            ['AED', 'درهم إماراتي', 'UAE Dirham', 'د.إ', 2],
        ] as [$code, $nameAr, $nameEn, $symbol, $decimals]) {
            Currency::query()->updateOrCreate(
                ['code' => $code],
                ['name_ar' => $nameAr, 'name_en' => $nameEn, 'symbol' => $symbol, 'decimal_places' => $decimals, 'is_active' => true]
            );
        }

        Company::query()->whereNull('currency_id')->each(function (Company $company) {
            $currency = Currency::query()
                ->where('code', $company->currency_code ?: 'EGP')
                ->where('is_active', true)
                ->first()
                ?? Currency::query()->where('code', 'EGP')->where('is_active', true)->first();

            $company->forceFill(['currency_id' => $currency?->id])->save();
        });

        foreach ([
            ['piece', 'قطعة', 'قطعة', 'quantity', 0],
            ['roll', 'رول', 'رول', 'package', 0],
            ['meter', 'متر', 'م', 'length', 3],
            ['square_meter', 'متر مربع', 'م²', 'area', 3],
            ['centimeter', 'سنتيمتر', 'سم', 'length', 2],
            ['liter', 'لتر', 'ل', 'volume', 3],
            ['box', 'صندوق', 'صندوق', 'package', 0],
            ['pack', 'عبوة', 'عبوة', 'package', 0],
        ] as [$code, $name, $symbol, $type, $decimals]) {
            Unit::query()->updateOrCreate(
                ['company_id' => null, 'code' => $code],
                [
                    'name' => $name, 'symbol' => $symbol, 'unit_type' => $type,
                    'decimal_places' => $decimals, 'is_system' => true, 'is_active' => true,
                ]
            );
        }

        foreach (['small', 'medium', 'large', 'suv', 'luxury', 'sports'] as $order => $code) {
            VehicleSize::query()->updateOrCreate(
                ['company_id' => null, 'code' => $code],
                ['name' => ucfirst($code), 'sort_order' => $order + 1, 'is_system' => true, 'is_active' => true]
            );
        }

        foreach (['sedan', 'suv', 'coupe', 'hatchback', 'pickup', 'van', 'sports', 'luxury'] as $order => $code) {
            VehicleType::query()->updateOrCreate(
                ['company_id' => null, 'code' => $code],
                ['name' => ucfirst($code), 'sort_order' => $order + 1, 'is_system' => true, 'is_active' => true]
            );
        }
    }
}
