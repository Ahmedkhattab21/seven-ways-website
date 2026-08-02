<?php

namespace App\Services\ProductionBootstrap;

use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Branch;
use App\Models\BranchAccountingSetting;
use App\Models\BranchProduct;
use App\Models\BranchProductPrice;
use App\Models\CashBox;
use App\Models\CashBoxCustodian;
use App\Models\Company;
use App\Models\Currency;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BranchAccountingSettingsService;
use App\Services\BranchResponsibleUserService;
use App\Services\DocumentNumberService;
use App\Services\FinancialHistoryInspector;
use App\Support\DocumentSequenceCatalog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SevenWaysProductionBootstrap
{
    private array $options = [];

    private array $changes = [];

    private array $warnings = [];

    private array $errors = [];

    private ?Company $company = null;

    public function configure(array $options = []): self
    {
        $this->options = array_merge([
            'rotate_passwords' => false,
            'replace_accounting_mappings' => false,
            'authorized_execution' => false,
            'read_only' => false,
        ], $options);
        $this->changes = [];
        $this->warnings = [];
        $this->errors = [];
        $this->company = null;

        return $this;
    }

    public function runAll(): array
    {
        $this->reference();
        $this->branches();
        $this->users();
        $this->catalog();
        $this->warehouses();
        $this->sequences();
        $this->accountingMappings();
        $this->treasury();
        $this->products();

        return $this->snapshot();
    }

    public function reference(): void
    {
        $company = $this->company();
        $egp = $this->upsert('reference', Currency::class, ['code' => 'EGP'], [
            'name_ar' => 'جنيه مصري', 'name_en' => 'Egyptian Pound', 'symbol' => 'EGP',
            'decimal_places' => 2, 'is_active' => true,
        ]);
        $history = app(FinancialHistoryInspector::class)->hasPostedFinancialMovements($company);
        if (! $history) {
            $company->forceFill([
                'country_code' => 'EG', 'currency_code' => 'EGP', 'currency_id' => $egp->id,
                'timezone' => 'Africa/Cairo', 'default_language' => 'ar', 'ui_direction' => 'rtl',
            ])->save();
        } elseif ($company->currency_code !== 'EGP') {
            $this->warnings[] = 'Company currency was preserved because posted financial history exists.';
        }

        $vat = $this->upsert('reference', Tax::class, [
            'company_id' => $company->id, 'code' => 'VAT14-EG',
        ], [
            'name' => 'ضريبة القيمة المضافة المصرية 14%', 'rate' => 14, 'tax_type' => 'both',
            'is_default' => ! $history, 'is_inclusive' => false, 'is_active' => true,
        ], true);
        if (! $history) {
            $company->forceFill(['default_tax_id' => $vat->id])->save();
        }

        foreach ([
            ['CASH', 'نقدي', 'cash', false, true],
            ['CARD', 'بطاقة', 'card', true, false],
            ['BANK', 'تحويل بنكي', 'bank_transfer', true, false],
            ['ONLINE', 'دفع إلكتروني', 'online', true, false],
            ['CREDIT', 'آجل', 'credit', false, false],
        ] as $sort => [$code, $name, $type, $reference, $cash]) {
            $this->upsert('reference', PaymentMethod::class, [
                'company_id' => $company->id, 'code' => $code,
            ], [
                'name' => $name, 'type' => $type, 'requires_reference' => $reference,
                'is_cash' => $cash, 'is_active' => true, 'sort_order' => $sort + 1,
            ], true);
        }

        foreach ([
            'PIECE' => ['قطعة', 'قطعة', 'quantity', 0],
            'ROLL' => ['رول', 'رول', 'quantity', 0],
            'METER' => ['متر', 'م', 'length', 3],
            'LITER' => ['لتر', 'لتر', 'volume', 3],
            'PACKAGE' => ['عبوة', 'عبوة', 'quantity', 0],
        ] as $code => [$name, $symbol, $type, $decimals]) {
            $this->upsert('reference', Unit::class, ['company_id' => $company->id, 'code' => $code], [
                'name' => $name, 'symbol' => $symbol, 'unit_type' => $type,
                'decimal_places' => $decimals, 'is_system' => false, 'is_active' => true,
            ]);
        }

        $start = Carbon::today()->month((int) $company->fiscal_year_start_month)->startOfMonth();
        if ($start->isFuture()) {
            $start->subYear();
        }
        $end = $start->copy()->addYear()->subDay();
        $this->upsert('reference', FiscalYear::class, [
            'company_id' => $company->id, 'start_date' => $start->toDateString(),
        ], [
            'name' => $start->format('Y').'/'.$end->format('Y'), 'end_date' => $end->toDateString(),
            'status' => 'open', 'is_current' => true,
        ]);
    }

    public function branches(): void
    {
        $company = $this->company();
        $rows = [
            'CAI-MAIN' => [
                'name' => 'مدينة نصر', 'commercial_name' => 'Seven Ways Nasr City',
                'address' => 'محطة بنزين وطنية - بجوار مسجد السلام، الوفاء والأمل، مدينة نصر',
                'phone' => '+201099025564', 'is_main' => true, 'is_active' => true,
            ],
            'ALEX' => [
                'name' => 'فرع الإسكندرية', 'commercial_name' => 'Seven Ways Alexandria',
                'address' => 'الإسكندرية، مصر', 'phone' => '+201095584458',
                'is_main' => false, 'is_active' => true,
            ],
        ];
        foreach ($rows as $code => $values) {
            $this->upsert('branches', Branch::class, [
                'company_id' => $company->id, 'code' => $code,
            ], $values, true);
        }
        Branch::query()->where('company_id', $company->id)->where('code', '!=', 'CAI-MAIN')
            ->where('is_main', true)->update(['is_main' => false]);
        $cash = PaymentMethod::query()->where('company_id', $company->id)->where('code', 'CASH')->first();
        $vat = Tax::query()->where('company_id', $company->id)->where('code', 'VAT14-EG')->first();
        $hasHistory = app(FinancialHistoryInspector::class)->hasPostedFinancialMovements($company);
        foreach (Branch::query()->where('company_id', $company->id)->whereIn('code', array_keys($rows))->get() as $branch) {
            $settings = $branch->settings()->firstOrNew();
            $settings->forceFill([
                'branch_id' => $branch->id,
                'default_payment_method_id' => $cash?->id,
                'default_tax_id' => $hasHistory ? $settings->default_tax_id : $vat?->id,
                'allow_negative_stock' => false,
            ])->save();
        }
    }

    public function users(): void
    {
        $company = $this->company();
        $configs = (array) config('sevenways_production.users', []);
        $missing = [];
        foreach ($configs as $key => $config) {
            if (blank($config['email'] ?? null)) {
                $missing[] = $this->userEnvironmentName($key, 'EMAIL');
            }
            if (blank($config['password'] ?? null)) {
                $missing[] = $this->userEnvironmentName($key, 'PASSWORD');
            }
        }
        if ($missing) {
            throw new RuntimeException('Missing required environment variables: '.implode(', ', $missing));
        }

        $branches = Branch::query()->where('company_id', $company->id)->whereIn('code', ['CAI-MAIN', 'ALEX'])
            ->get()->keyBy('code');
        foreach ($configs as $key => $config) {
            $email = mb_strtolower(trim((string) $config['email']));
            $user = User::query()->where('email', $email)->first() ?? new User;
            if ($user->exists && $user->company_id && (int) $user->company_id !== (int) $company->id) {
                throw new RuntimeException("Bootstrap email belongs to another company: {$email}");
            }
            $created = ! $user->exists;
            $password = $created || $this->options['rotate_passwords']
                ? Hash::make((string) $config['password'])
                : $user->password;
            $branch = $branches->get($config['branch']);
            $user->forceFill([
                'name' => $config['name'], 'email' => $email, 'password' => $password,
                'company_id' => $company->id, 'branch_id' => $branch?->id, 'status' => 'active',
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
            $role = $this->companyRole($company, $config['role']);
            $user->roles()->syncWithoutDetaching([$role->id]);
            $this->changes['users'][] = [
                'id' => $user->id, 'name' => $user->name, 'email' => $email,
                'role' => $role->name, 'default_branch' => $branch?->code,
                'password_source' => 'Environment Variable',
                'password_rotated' => ! $created && (bool) $this->options['rotate_passwords'],
                'result' => $created ? 'Created' : ($user->wasChanged() ? 'Updated' : 'Unchanged'),
            ];

            $accessBranches = $config['role'] === 'general_manager'
                ? Branch::query()->where('company_id', $company->id)->where('is_active', true)->get()
                : ($config['role'] === 'accountant' ? $branches->values() : collect([$branch]));
            $pivot = [];
            foreach ($accessBranches->filter() as $accessBranch) {
                $pivot[$accessBranch->id] = [
                    'is_default' => $accessBranch->id === $branch?->id,
                    'can_view' => true,
                    'can_create' => $config['role'] !== 'accountant' || $role->permissions()->where('name', 'like', '%.create')->exists(),
                    'can_update' => $config['role'] !== 'accountant' || $role->permissions()->where('name', 'like', '%.update')->exists(),
                    'can_approve' => $config['role'] === 'general_manager' || $role->permissions()->where('name', 'like', '%.approve')->exists(),
                ];
            }
            if ($config['role'] === 'branch_manager') {
                $user->accessibleBranches()->sync($pivot);
            } else {
                $user->accessibleBranches()->syncWithoutDetaching($pivot);
            }

            if ($config['role'] === 'branch_manager' && $branch) {
                app(TenantContext::class)->initialize($user);
                app(BranchResponsibleUserService::class)->assign($branch, $user);
                $user->accessibleBranches()->updateExistingPivot($branch->id, $pivot[$branch->id]);
            }
            $changeIndex = array_key_last($this->changes['users']);
            $this->changes['users'][$changeIndex]['accessible_branches'] = $accessBranches->filter()->pluck('code')->values()->all();
            $this->changes['users'][$changeIndex]['responsible_branch'] = $config['role'] === 'branch_manager'
                ? $branch?->code : null;
        }
    }

    public function catalog(): void
    {
        $company = $this->company();
        foreach ([
            'PPF' => ['أفلام حماية الطلاء PPF', 'Paint Protection Film'],
            'WINDOW-TINT' => ['العازل الحراري للزجاج', 'Automotive Window Tint'],
            'NANO-CERAMIC' => ['النانو سيراميك', 'Nano Ceramic Coating'],
            'POLISHING-DETAILING' => ['التلميعات والعناية بالسيارة', 'Polishing and Car Detailing'],
        ] as $code => [$arabic, $english]) {
            $this->upsert('categories', ProductCategory::class, [
                'company_id' => $company->id, 'code' => $code,
            ], [
                'name' => $arabic, 'description' => $english, 'is_active' => true,
            ], true);
        }
        foreach ([
            'PROJECT-3' => 'Project 3', 'HEXIS' => 'HEXIS', 'XPEL' => 'XPEL', '3M' => '3M',
            'LAYER-PLUS' => 'Layer+', 'DYNOSTEK' => 'DYNOstek', 'SUPER-PRO' => 'Super Pro',
            'LLUMAR' => 'LLumar', 'RUPES' => 'RUPES', 'CARPRO' => 'CarPro', 'SONAX' => 'SONAX',
            'KOCH-CHEMIE' => 'Koch-Chemie', 'MEGUIARS' => 'Meguiar’s', 'ZEROX' => 'Zerox',
        ] as $code => $name) {
            $this->upsert('brands', ProductBrand::class, [
                'company_id' => $company->id, 'code' => $code,
            ], ['name' => $name, 'is_active' => true], true);
        }
    }

    public function warehouses(): void
    {
        $company = $this->company();
        foreach ([
            'CAI-MAIN' => ['NASR-MAIN-WH', 'المخزن الرئيسي - مدينة نصر'],
            'ALEX' => ['ALEX-MAIN-WH', 'المخزن الرئيسي - فرع الإسكندرية'],
        ] as $branchCode => [$code, $name]) {
            $branch = Branch::query()->where('company_id', $company->id)->where('code', $branchCode)->firstOrFail();
            $warehouse = Warehouse::withTrashed()->where('company_id', $company->id)
                ->where('branch_id', $branch->id)->where('is_main', true)->first();
            $warehouse ??= Warehouse::withTrashed()->where('company_id', $company->id)->where('code', $code)->first();
            $warehouse ??= new Warehouse;
            $this->saveTracked('warehouses', $warehouse, [
                'company_id' => $company->id, 'branch_id' => $branch->id, 'code' => $code, 'name' => $name,
                'warehouse_type' => 'main', 'is_main' => true, 'is_active' => true, 'is_system' => false,
                'allows_sale_issue' => true, 'allows_work_order_issue' => false,
                'allows_damaged_stock' => false, 'deleted_at' => null,
            ]);
        }
    }

    public function sequences(): void
    {
        $company = $this->company();
        $year = now()->format('Y');
        foreach (Branch::query()->where('company_id', $company->id)->where('is_active', true)->get() as $branch) {
            foreach (DocumentSequenceCatalog::production() as $type => $definition) {
                $periodKey = $definition['reset_period'] === 'yearly' ? $year : null;
                $scopeKey = DocumentNumberService::scopeKey($company->id, $branch->id, $type, $periodKey);
                $sequence = DocumentSequence::query()->where('scope_key', $scopeKey)->first() ?? new DocumentSequence;
                $values = [
                    'company_id' => $company->id, 'branch_id' => $branch->id, 'document_type' => $type,
                    'prefix' => '{BRANCH}-'.$definition['short_code'].'-'.($periodKey ? '{YYYY}-' : ''),
                    'padding' => 6, 'reset_period' => $definition['reset_period'],
                    'period_key' => $periodKey, 'scope_key' => $scopeKey, 'is_active' => true,
                ];
                if (! $sequence->exists) {
                    $values['current_number'] = 0;
                }
                $before = $sequence->exists ? (int) $sequence->current_number : 0;
                $this->saveTracked('sequences', $sequence, $values, [
                    'branch' => $branch->code, 'document_type' => $type, 'current_number_before' => $before,
                ]);
                if ($sequence->exists && (int) $sequence->current_number !== $before) {
                    throw new RuntimeException("Existing sequence {$scopeKey} current_number changed unexpectedly.");
                }
            }
        }
    }

    public function accountingMappings(): void
    {
        $company = $this->company();
        $actor = User::query()->where('company_id', $company->id)->orderBy('id')->first();
        if ($actor) {
            $this->supplierAdvanceAccount($company, $actor);
        }
        foreach (Branch::query()->where('company_id', $company->id)->where('is_active', true)->get() as $branch) {
            $cash = $actor ? $this->branchCashAccount($company, $branch, $actor) : null;
            $codes = [
                'cash_account_id' => $cash?->account_code,
                'bank_account_id' => '112000', 'accounts_receivable_account_id' => '113000',
                'accounts_payable_account_id' => '211000', 'sales_revenue_account_id' => '420000',
                'service_revenue_account_id' => '410000', 'product_revenue_account_id' => '420000',
                'sales_discount_account_id' => '430000', 'sales_return_account_id' => '430000',
                'inventory_account_id' => '114000', 'cost_of_goods_sold_account_id' => '500000',
                'inventory_adjustment_account_id' => '650000', 'purchase_account_id' => '520000',
                'purchase_return_account_id' => '520000', 'vat_input_account_id' => '115000',
                'vat_output_account_id' => '212000', 'customer_advance_account_id' => '213000',
                'supplier_advance_account_id' => '214000', 'rounding_account_id' => '650000',
            ];
            $settings = BranchAccountingSetting::query()->firstOrNew(['branch_id' => $branch->id]);
            $values = ['company_id' => $company->id, 'branch_id' => $branch->id];
            foreach (BranchAccountingSettingsService::ACCOUNT_COLUMNS as $column) {
                $account = isset($codes[$column]) ? Account::query()->where('company_id', $company->id)
                    ->where('account_code', $codes[$column])->where('is_active', true)->where('is_posting', true)->first() : null;
                if (! $account) {
                    $this->warnings[] = "Missing accounting mapping candidate {$column} for branch {$branch->code}.";

                    continue;
                }
                if (! $settings->{$column} || $this->options['replace_accounting_mappings']) {
                    $values[$column] = $account->id;
                }
            }
            $this->saveTracked('accounting', $settings, $values, ['branch' => $branch->code]);
        }
    }

    public function treasury(): void
    {
        $company = $this->company();
        $currency = Currency::query()->where('code', 'EGP')->where('is_active', true)->firstOrFail();
        foreach ([
            'CAI-MAIN' => ['NASR-CASH-01', 'الخزينة الرئيسية - مدينة نصر'],
            'ALEX' => ['ALEX-CASH-01', 'الخزينة الرئيسية - فرع الإسكندرية'],
        ] as $branchCode => [$code, $name]) {
            $branch = Branch::query()->where('company_id', $company->id)->where('code', $branchCode)->firstOrFail();
            $manager = $branch->responsibleUser;
            $gl = Account::query()->where('company_id', $company->id)
                ->where('account_code', '111-CASH-'.$branch->code)->where('is_active', true)->where('is_posting', true)->first();
            if (! $manager || ! $gl) {
                $this->warnings[] = "Cash box {$code} was skipped because its manager or cash GL account is missing.";

                continue;
            }
            $box = CashBox::withTrashed()->where('company_id', $company->id)->where('code', $code)->first();
            $box ??= CashBox::withTrashed()->where('company_id', $company->id)
                ->where('branch_id', $branch->id)->where('is_primary', true)->first();
            $box ??= new CashBox;
            $this->saveTracked('cash_boxes', $box, [
                'company_id' => $company->id, 'branch_id' => $branch->id, 'code' => $code, 'name' => $name,
                'currency_id' => $currency->id, 'gl_account_id' => $gl->id, 'status' => 'active',
                'is_primary' => true, 'allows_receipts' => true, 'allows_payments' => true,
                'requires_shift_opening' => false, 'created_by' => $box->created_by ?: $manager->id,
                'updated_by' => $manager->id, 'deleted_at' => null,
            ]);
            $custodian = CashBoxCustodian::query()->where('company_id', $company->id)
                ->where('cash_box_id', $box->id)->where('user_id', $manager->id)->where('is_active', true)->first()
                ?? new CashBoxCustodian;
            $this->saveTracked('custodians', $custodian, [
                'company_id' => $company->id, 'cash_box_id' => $box->id, 'user_id' => $manager->id,
                'valid_from' => $custodian->valid_from ?: today(), 'can_receive' => true, 'can_pay' => true,
                'can_transfer' => false, 'payment_limit' => null, 'is_primary' => true, 'is_active' => true,
                'assigned_by' => $custodian->assigned_by ?: $manager->id,
            ]);
        }
    }

    public function products(): void
    {
        $company = $this->company();
        $rows = require database_path('data/sevenways_products.php');
        if (! is_array($rows)) {
            throw new RuntimeException('database/data/sevenways_products.php must return an array.');
        }
        foreach ($rows as $index => $row) {
            try {
                foreach (['sku', 'name', 'category_code', 'purchase_unit_code', 'stock_unit_code', 'sales_unit_code'] as $required) {
                    if (blank($row[$required] ?? null)) {
                        throw new RuntimeException("Product row {$index} is missing {$required}.");
                    }
                }
                $category = ProductCategory::query()->where('company_id', $company->id)->where('code', $row['category_code'])->firstOrFail();
                $brand = blank($row['brand_code'] ?? null) ? null : ProductBrand::query()
                    ->where('company_id', $company->id)->where('code', $row['brand_code'])->firstOrFail();
                $units = Unit::query()->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $company->id))
                    ->whereIn('code', [$row['purchase_unit_code'], $row['stock_unit_code'], $row['sales_unit_code']])->get()->keyBy('code');
                $warranty = (array) ($row['warranty'] ?? []);
                $product = Product::withTrashed()->where('company_id', $company->id)->where('sku', $row['sku'])->first() ?? new Product;
                $this->saveTracked('products', $product, [
                    'company_id' => $company->id, 'category_id' => $category->id, 'brand_id' => $brand?->id,
                    'sku' => $row['sku'], 'barcode' => $row['barcode'] ?? null, 'name' => $row['name'],
                    'description' => $row['description'] ?? null, 'product_type' => $row['product_type'] ?? 'standard',
                    'tracking_type' => $row['tracking_type'] ?? 'quantity',
                    'purchase_unit_id' => $units[$row['purchase_unit_code']]->id,
                    'stock_unit_id' => $units[$row['stock_unit_code']]->id,
                    'sale_unit_id' => $units[$row['sales_unit_code']]->id,
                    'costing_method' => $row['costing_method'] ?? 'weighted_average',
                    'standard_cost' => $row['standard_cost'] ?? null,
                    'default_sale_price' => ($row['global_price'] ?? false) ? ($row['base_selling_price'] ?? null) : null,
                    'minimum_stock' => $row['minimum_stock'] ?? 0, 'maximum_stock' => $row['maximum_stock'] ?? null,
                    'reorder_quantity' => $row['reorder_quantity'] ?? null,
                    'is_sellable' => $row['is_sellable'] ?? true, 'is_purchasable' => $row['is_purchasable'] ?? true,
                    'is_consumable' => $row['is_consumable'] ?? false, 'is_active' => $row['is_active'] ?? true,
                    'requires_warranty' => $warranty['applies'] ?? false,
                    'default_warranty_film_type' => $warranty['film_type'] ?? null,
                    'default_warranty_duration_value' => $warranty['duration_value'] ?? null,
                    'default_warranty_duration_unit' => $warranty['duration_unit'] ?? null,
                    'default_warranty_application_area' => $warranty['application_area'] ?? null,
                    'default_warranty_terms' => $warranty['terms'] ?? null,
                    'default_warranty_notes' => $warranty['notes'] ?? null, 'deleted_at' => null,
                ]);
                $this->productBranchesAndPricing($company, $product, $row);
                $this->productPromotion($company, $product, (array) ($row['promotion'] ?? []));
            } catch (Throwable $exception) {
                $this->errors[] = $exception->getMessage();
            }
        }
    }

    public function verify(): array
    {
        $this->options['read_only'] = true;
        $before = $this->databaseFingerprint();
        $company = $this->company();
        $issues = [];
        $branches = Branch::query()->where('company_id', $company->id)->get()->keyBy('code');
        foreach (['CAI-MAIN', 'ALEX'] as $code) {
            if (! $branches->has($code)) {
                $issues[] = "Missing branch {$code}.";
            }
        }
        if ($branches->where('is_main', true)->count() !== 1 || ! $branches->get('CAI-MAIN')?->is_main) {
            $issues[] = 'CAI-MAIN must be the only main branch.';
        }
        foreach ($branches->only(['CAI-MAIN', 'ALEX']) as $branch) {
            if (! $branch->responsible_user_id) {
                $issues[] = "Missing responsible manager for {$branch->code}.";
            }
            if (! Warehouse::query()->where('company_id', $company->id)->where('branch_id', $branch->id)
                ->where('is_main', true)->where('is_active', true)->where('allows_sale_issue', true)->exists()) {
                $issues[] = "Missing valid main warehouse for {$branch->code}.";
            }
            if (! CashBox::query()->where('company_id', $company->id)->where('branch_id', $branch->id)
                ->where('is_primary', true)->where('status', 'active')->exists()) {
                $issues[] = "Missing active primary cash box for {$branch->code}.";
            }
            foreach (DocumentSequenceCatalog::production() as $type => $definition) {
                $period = $definition['reset_period'] === 'yearly' ? now()->format('Y') : null;
                if (! DocumentSequence::query()->where('scope_key', DocumentNumberService::scopeKey(
                    $company->id, $branch->id, $type, $period
                ))->where('is_active', true)->exists()) {
                    $issues[] = "Missing sequence {$type} for {$branch->code}.";
                }
            }
            $mapping = BranchAccountingSetting::query()->where('branch_id', $branch->id)->first();
            foreach (BranchAccountingSettingsService::ACCOUNT_COLUMNS as $column) {
                if (! $mapping?->{$column}) {
                    $issues[] = "Missing accounting mapping {$column} for {$branch->code}.";
                }
            }
        }
        foreach (array_keys(config('sevenways_production.users', [])) as $key) {
            $email = config("sevenways_production.users.{$key}.email");
            $user = $email ? User::query()->where('company_id', $company->id)->where('email', $email)->first() : null;
            if ($email && ! $user) {
                $issues[] = "Missing production user {$key}.";

                continue;
            }
            if (! $user) {
                continue;
            }
            $actual = $user->accessibleBranches()->wherePivot('can_view', true)->pluck('code')->sort()->values()->all();
            $expected = match ($key) {
                'nasr_manager' => ['CAI-MAIN'],
                'alex_manager' => ['ALEX'],
                'accountant' => ['ALEX', 'CAI-MAIN'],
                'general_manager' => Branch::query()->where('company_id', $company->id)->where('is_active', true)
                    ->pluck('code')->sort()->values()->all(),
                default => $actual,
            };
            sort($expected);
            if ($actual !== $expected) {
                $issues[] = "Invalid branch access for {$key}.";
            }
        }
        foreach (['VAT14-EG'] as $code) {
            if (! Tax::query()->where('company_id', $company->id)->where('code', $code)->where('is_active', true)->exists()) {
                $issues[] = "Missing reference {$code}.";
            }
        }
        foreach (['PPF', 'WINDOW-TINT', 'NANO-CERAMIC', 'POLISHING-DETAILING'] as $code) {
            if (! ProductCategory::query()->where('company_id', $company->id)->where('code', $code)->where('is_active', true)->exists()) {
                $issues[] = "Missing product category {$code}.";
            }
        }
        if (DocumentSequence::query()->select('scope_key')->groupBy('scope_key')->havingRaw('COUNT(*) > 1')->exists()) {
            $issues[] = 'Duplicate document sequence scope keys exist.';
        }
        $productRows = (array) require database_path('data/sevenways_products.php');
        $expectedSkus = collect($productRows)->pluck('sku')->filter()->values();
        if (Product::query()->where('company_id', $company->id)->whereNotIn('sku', $expectedSkus)->exists()) {
            $this->warnings[] = 'Existing products outside the bootstrap file were preserved.';
        }
        if ($before !== $this->databaseFingerprint()) {
            $issues[] = 'Verification changed database state.';
        }

        return ['status' => $issues ? 'FAILED' : 'READY', 'company_id' => $company->id, 'issues' => $issues];
    }

    public function snapshot(): array
    {
        return [
            'status' => $this->errors ? 'FAILED' : ($this->warnings ? 'READY WITH WARNINGS' : 'READY'),
            'company_id' => $this->company?->id,
            'company_name' => $this->company?->name,
            'changes' => $this->changes,
            'warnings' => array_values(array_unique($this->warnings)),
            'errors' => array_values(array_unique($this->errors)),
            'document_types' => array_keys(DocumentSequenceCatalog::production()),
            'sequence_count_per_branch' => count(DocumentSequenceCatalog::production()),
            'product_data_count' => is_file(database_path('data/sevenways_products.php'))
                ? count((array) require database_path('data/sevenways_products.php')) : 0,
        ];
    }

    public function saveReport(array $result, string $mode): string
    {
        $timestamp = now()->format('Ymd-His');
        $path = "private/production-bootstrap-reports/sevenways-production-bootstrap-{$timestamp}.md";
        $lines = [
            '# Seven Ways Production Bootstrap', '',
            '- Date: '.now()->toIso8601String(),
            '- Environment: '.app()->environment(),
            '- Mode: '.$mode,
            '- Company ID: '.($result['company_id'] ?? '—'),
            '- Company: '.($result['company_name'] ?? '—'),
            '- Commit SHA: '.$this->commitSha(),
            '- Result: '.$result['status'], '',
        ];
        foreach (['branches', 'users', 'warehouses', 'cash_boxes', 'custodians', 'sequences', 'reference', 'categories', 'brands', 'products', 'accounting'] as $section) {
            $lines[] = '## '.ucwords(str_replace('_', ' ', $section));
            $lines[] = '';
            $lines[] = '```json';
            $lines[] = json_encode($result['changes'][$section] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $lines[] = '```';
            $lines[] = '';
        }
        $lines[] = '## Document Types';
        $lines[] = '';
        $lines[] = implode(', ', $result['document_types'] ?? []);
        $lines[] = '';
        $lines[] = '## Warnings';
        $lines[] = '';
        foreach ($result['warnings'] ?? [] as $warning) {
            $lines[] = '- '.$warning;
        }
        $lines[] = '';
        $lines[] = '## Errors';
        $lines[] = '';
        foreach ($result['errors'] ?? [] as $error) {
            $lines[] = '- '.$error;
        }
        Storage::disk('local')->put($path, implode(PHP_EOL, $lines));

        return storage_path('app/'.$path);
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    private function company(): Company
    {
        if ($this->company) {
            return $this->company;
        }
        if (app()->environment('production')
            && ! ($this->options['authorized_execution'] ?? false)
            && ! ($this->options['read_only'] ?? false)
            && ! (config('sevenways_production.enabled') && in_array('--force', $_SERVER['argv'] ?? [], true))) {
            throw new RuntimeException('Production seeders require SEVENWAYS_PRODUCTION_BOOTSTRAP=true and --force; use the bootstrap command.');
        }
        $query = Company::query();
        $id = config('sevenways_production.company_id');
        $this->company = $id ? $query->find($id) : $query->where('name', 'Seven Ways')->first();
        if (! $this->company) {
            throw new RuntimeException('Seven Ways company was not found. Set SEVENWAYS_COMPANY_ID or create it explicitly first.');
        }

        return $this->company;
    }

    private function companyRole(Company $company, string $name): Role
    {
        $role = Role::query()->where('company_id', $company->id)->where('name', $name)->first();
        if ($role) {
            return $role;
        }
        $template = Role::query()->whereNull('company_id')->where('name', $name)->firstOrFail();
        $role = Role::query()->create([
            'company_id' => $company->id, 'name' => $name, 'display_name' => $template->display_name,
            'scope' => $template->scope, 'is_system' => true, 'is_active' => true,
        ]);
        $role->permissions()->syncWithoutDetaching($template->permissions()->pluck('permissions.id'));

        return $role;
    }

    private function branchCashAccount(Company $company, Branch $branch, User $actor): ?Account
    {
        $parent = Account::query()->where('company_id', $company->id)->where('account_code', '110000')->first();
        $group = AccountGroup::query()->where('company_id', $company->id)->where('code', '111')->first();
        if (! $parent || ! $group) {
            $this->warnings[] = "Cash account for {$branch->code} was not created because the chart of accounts is incomplete.";

            return null;
        }
        $code = '111-CASH-'.$branch->code;
        $account = Account::withTrashed()->where('company_id', $company->id)->where('account_code', $code)->first() ?? new Account;
        $this->saveTracked('accounts', $account, [
            'company_id' => $company->id, 'account_type_id' => $group->account_type_id,
            'account_group_id' => $group->id, 'parent_account_id' => $parent->id, 'account_code' => $code,
            'name_ar' => 'خزينة '.$branch->name, 'account_level' => $parent->account_level + 1,
            'account_path' => $account->account_path, 'normal_balance' => 'debit', 'is_header' => false,
            'is_posting' => true, 'currency_id' => $company->currency_id, 'requires_branch' => true,
            'is_control_account' => false, 'is_bank_account' => false, 'is_cash_account' => true,
            'is_inventory_account' => false, 'is_tax_account' => false, 'is_active' => true,
            'allow_manual_entry' => false, 'created_by' => $account->created_by ?: $actor->id, 'deleted_at' => null,
        ]);
        if (! $account->account_path) {
            $account->forceFill(['account_path' => $parent->account_path.'/'.$account->id])->saveQuietly();
        }

        return $account;
    }

    private function supplierAdvanceAccount(Company $company, User $actor): ?Account
    {
        $existing = Account::query()->where('company_id', $company->id)->where('account_code', '214000')->first();
        if ($existing) {
            return $existing;
        }
        $parent = Account::query()->where('company_id', $company->id)->where('account_code', '210000')->first();
        $group = AccountGroup::query()->where('company_id', $company->id)->where('code', '211')->first();
        if (! $parent || ! $group) {
            $this->warnings[] = 'Supplier advance account was not created because the chart of accounts is incomplete.';

            return null;
        }
        $account = new Account;
        $this->saveTracked('accounts', $account, [
            'company_id' => $company->id, 'account_type_id' => $group->account_type_id,
            'account_group_id' => $group->id, 'parent_account_id' => $parent->id,
            'account_code' => '214000', 'name_ar' => 'دفعات مقدمة للموردين',
            'account_level' => $parent->account_level + 1, 'normal_balance' => 'credit',
            'is_header' => false, 'is_posting' => true, 'currency_id' => $company->currency_id,
            'is_control_account' => true, 'control_type' => 'supplier_advances',
            'is_bank_account' => false, 'is_cash_account' => false, 'is_inventory_account' => false,
            'is_tax_account' => false, 'is_active' => true, 'allow_manual_entry' => false,
            'created_by' => $actor->id,
        ]);
        $account->forceFill(['account_path' => $parent->account_path.'/'.$account->id])->saveQuietly();

        return $account;
    }

    private function productBranchesAndPricing(Company $company, Product $product, array $row): void
    {
        foreach ((array) ($row['branch_prices'] ?? []) as $branchCode => $settings) {
            $branch = Branch::query()->where('company_id', $company->id)->where('code', $branchCode)->firstOrFail();
            $warehouse = Warehouse::query()->where('company_id', $company->id)->where('branch_id', $branch->id)
                ->where('is_main', true)->first();
            $this->upsert('branch_products', BranchProduct::class, [
                'company_id' => $company->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            ], [
                'default_sales_warehouse_id' => $warehouse?->id,
                'is_available' => $settings['is_available'] ?? true,
                'is_sellable' => $settings['is_sellable'] ?? true,
            ]);
            if (array_key_exists('price', $settings) && $settings['price'] !== null) {
                $this->upsert('branch_prices', BranchProductPrice::class, [
                    'company_id' => $company->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
                    'effective_from' => $settings['effective_from'] ?? today()->toDateString(),
                ], [
                    'price' => $settings['price'], 'minimum_price' => $settings['minimum_price'] ?? null,
                    'effective_to' => $settings['effective_to'] ?? null, 'priority' => $settings['priority'] ?? 0,
                    'is_active' => true,
                ]);
            }
        }
    }

    private function productPromotion(Company $company, Product $product, array $promotion): void
    {
        if (! $promotion) {
            return;
        }
        foreach (['code', 'name', 'discount_type', 'discount_value', 'starts_at', 'ends_at'] as $field) {
            if (blank($promotion[$field] ?? null)) {
                throw new RuntimeException("Promotion for {$product->sku} is missing {$field}.");
            }
        }
        $model = $this->upsert('promotions', Promotion::class, [
            'company_id' => $company->id, 'code' => $promotion['code'],
        ], [
            'name' => $promotion['name'], 'description' => $promotion['description'] ?? null,
            'promotion_type' => 'products', 'discount_type' => $promotion['discount_type'],
            'discount_value' => $promotion['discount_value'], 'start_at' => $promotion['starts_at'],
            'end_at' => $promotion['ends_at'], 'is_active' => true,
        ], true);
        $model->products()->syncWithoutDetaching([$product->id]);
        $branchIds = Branch::query()->where('company_id', $company->id)
            ->whereIn('code', (array) ($promotion['branches'] ?? []))->pluck('id');
        if ($branchIds->isNotEmpty()) {
            $model->branches()->syncWithoutDetaching($branchIds);
        }
    }

    private function upsert(
        string $section,
        string $modelClass,
        array $keys,
        array $values,
        bool $withTrashed = false
    ): Model {
        $query = $withTrashed ? $modelClass::withTrashed() : $modelClass::query();
        $model = $query->where($keys)->first() ?? new $modelClass;

        return $this->saveTracked($section, $model, [...$keys, ...$values]);
    }

    private function saveTracked(string $section, Model $model, array $values, array $extra = []): Model
    {
        $created = ! $model->exists;
        $model->forceFill($values)->save();
        $result = $created ? 'Created' : ($model->wasChanged() ? 'Updated' : 'Unchanged');
        $this->changes[$section][] = [
            ...$extra, ...$this->reportAttributes($model), 'id' => $model->getKey(), 'result' => $result,
        ];

        return $model;
    }

    private function reportAttributes(Model $model): array
    {
        return match (true) {
            $model instanceof Branch => [
                'code' => $model->code, 'name' => $model->name, 'main' => (bool) $model->is_main,
                'active' => (bool) $model->is_active, 'phone' => $model->phone, 'address' => $model->address,
            ],
            $model instanceof Warehouse => [
                'code' => $model->code, 'name' => $model->name, 'branch_id' => $model->branch_id,
                'main' => (bool) $model->is_main, 'active' => (bool) $model->is_active,
                'allows_sale_issue' => (bool) $model->allows_sale_issue,
                'current_stock_rows' => $model->exists ? $model->balances()->count() : 0,
            ],
            $model instanceof CashBox => [
                'code' => $model->code, 'name' => $model->name, 'branch_id' => $model->branch_id,
                'currency_id' => $model->currency_id, 'gl_account_id' => $model->gl_account_id,
                'status' => $model->status, 'book_balance' => $this->cashBoxBookBalance($model),
            ],
            $model instanceof CashBoxCustodian => [
                'cash_box_id' => $model->cash_box_id, 'user_id' => $model->user_id,
                'can_receive' => (bool) $model->can_receive, 'can_pay' => (bool) $model->can_pay,
                'can_transfer' => (bool) $model->can_transfer, 'primary' => (bool) $model->is_primary,
            ],
            $model instanceof DocumentSequence => [
                'document_type' => $model->document_type, 'prefix' => $model->prefix,
                'reset_period' => $model->reset_period, 'period_key' => $model->period_key,
                'current_number_after' => (int) $model->current_number,
                'next_number' => (int) $model->current_number + 1, 'active' => (bool) $model->is_active,
            ],
            $model instanceof BranchAccountingSetting => [
                'branch_id' => $model->branch_id,
                'mappings' => collect(BranchAccountingSettingsService::ACCOUNT_COLUMNS)
                    ->mapWithKeys(fn (string $column) => [$column => $model->{$column}])->all(),
            ],
            $model instanceof ProductCategory, $model instanceof ProductBrand => [
                'code' => $model->code, 'name' => $model->name, 'active' => (bool) $model->is_active,
            ],
            $model instanceof Product => [
                'sku' => $model->sku, 'name' => $model->name, 'active' => (bool) $model->is_active,
            ],
            $model instanceof Currency, $model instanceof Tax, $model instanceof PaymentMethod, $model instanceof Unit => [
                'code' => $model->code, 'name' => $model->name ?? $model->name_ar ?? null,
                'active' => (bool) $model->is_active,
            ],
            default => [],
        };
    }

    private function cashBoxBookBalance(CashBox $box): string
    {
        $balance = DB::table('journal_entry_lines as lines')
            ->join('journal_entries as entries', 'entries.id', '=', 'lines.journal_entry_id')
            ->where('entries.company_id', $box->company_id)->where('entries.status', 'posted')
            ->where('lines.account_id', $box->gl_account_id)->where('lines.branch_id', $box->branch_id)
            ->selectRaw('COALESCE(SUM(lines.base_debit_amount - lines.base_credit_amount), 0) balance')
            ->value('balance');

        return number_format((float) $balance, 4, '.', '');
    }

    private function userEnvironmentName(string $key, string $suffix): string
    {
        return match ($key) {
            'nasr_manager' => "SEVENWAYS_NASR_MANAGER_{$suffix}",
            'alex_manager' => "SEVENWAYS_ALEX_MANAGER_{$suffix}",
            'accountant' => "SEVENWAYS_ACCOUNTANT_{$suffix}",
            'general_manager' => "SEVENWAYS_GENERAL_MANAGER_{$suffix}",
            default => 'SEVENWAYS_'.strtoupper($key)."_{$suffix}",
        };
    }

    private function databaseFingerprint(): string
    {
        $tables = ['companies', 'branches', 'users', 'warehouses', 'cash_boxes', 'document_sequences', 'products'];
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return hash('sha256', json_encode($counts));
    }

    private function commitSha(): string
    {
        $head = base_path('.git/HEAD');
        if (! is_file($head)) {
            return 'unknown';
        }
        $value = trim((string) file_get_contents($head));
        if (! str_starts_with($value, 'ref: ')) {
            return substr($value, 0, 40);
        }
        $ref = base_path('.git/'.substr($value, 5));

        return is_file($ref) ? trim((string) file_get_contents($ref)) : 'unknown';
    }
}
