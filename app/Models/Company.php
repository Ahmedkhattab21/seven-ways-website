<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends BaseModel
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $company) {
            $company->country_code ??= config('localization.default_country_code', 'EG');
            $company->currency_code ??= config('localization.default_currency_code', 'EGP');
            $company->timezone ??= config('localization.default_timezone', 'Africa/Cairo');
            if (! $company->currency_id) {
                $currency = Currency::query()->where('code', $company->currency_code)
                    ->where('is_active', true)->first();
                if ($currency) {
                    $company->currency_id = $currency->id;
                    $company->currency_code = $currency->code;
                }
            }
        });
    }

    protected $fillable = [
        'name', 'legal_name', 'commercial_registration', 'tax_number', 'email',
        'phone', 'logo_path', 'address', 'country_code', 'currency_code', 'currency_id',
        'timezone', 'fiscal_year_start_month', 'date_format', 'time_format',
        'money_decimal_places', 'default_language', 'ui_direction', 'default_tax_id', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'fiscal_year_start_month' => 'integer',
        'money_decimal_places' => 'integer',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function defaultTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'default_tax_id');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    public function fiscalYears(): HasMany
    {
        return $this->hasMany(FiscalYear::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function customerSources(): HasMany
    {
        return $this->hasMany(CustomerSource::class);
    }

    public function serviceCategories(): HasMany
    {
        return $this->hasMany(ServiceCategory::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function servicePackages(): HasMany
    {
        return $this->hasMany(ServicePackage::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }
}
