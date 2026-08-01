<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\AccountingPeriod;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\BankAccountBranchAccess;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\BranchService;
use App\Models\CashBox;
use App\Models\CashBoxCustodian;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Models\VehicleSize;
use App\Models\VehicleType;
use App\Models\Warehouse;
use App\Services\AccountantCashSessionPermissionReconciler;
use App\Services\DocumentNumberService;
use App\Services\UatEnvironmentGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SevenWaysUatSeeder extends Seeder
{
    private const COMPANY_NAME = 'Seven Ways UAT Egypt';

    private const PASSWORD = 'Uat@123456';

    private const USERS = [
        'owner' => ['uat.owner@sevenways.test', 'UAT Company Owner', 'company_owner', 'UAT-CAI', '*'],
        'general_manager' => ['uat.general.manager@sevenways.test', 'UAT General Manager', 'general_manager', 'UAT-CAI', '*'],
        'cairo_manager' => ['uat.cairo.manager@sevenways.test', 'UAT Cairo Manager', 'branch_manager', 'UAT-CAI', 'UAT-CAI'],
        'giza_manager' => ['uat.giza.manager@sevenways.test', 'UAT Giza Manager', 'branch_manager', 'UAT-GIZ', 'UAT-GIZ'],
        'accountant' => ['uat.accountant@sevenways.test', 'UAT Accountant', 'accountant', 'UAT-CAI', '*'],
        'treasury_manager' => ['uat.treasury.manager@sevenways.test', 'UAT Treasury Manager', 'uat_treasury_manager', 'UAT-CAI', 'UAT-CAI,UAT-GIZ'],
        'cairo_cashier' => ['uat.cairo.cashier@sevenways.test', 'UAT Cairo Cashier', 'uat_cashier', 'UAT-CAI', 'UAT-CAI'],
        'sales' => ['uat.sales@sevenways.test', 'UAT Sales', 'sales', 'UAT-CAI', 'UAT-CAI'],
        'warehouse' => ['uat.warehouse@sevenways.test', 'UAT Warehouse Keeper', 'warehouse_keeper', 'UAT-CAI', 'UAT-CAI'],
        'technician' => ['uat.technician@sevenways.test', 'UAT Technician', 'technician', 'UAT-CAI', 'UAT-CAI'],
        'quality' => ['uat.quality@sevenways.test', 'UAT Quality Controller', 'quality_controller', 'UAT-CAI', 'UAT-CAI'],
        'reception' => ['uat.reception@sevenways.test', 'UAT Reception', 'receptionist', 'UAT-CAI', 'UAT-CAI'],
        'viewer' => ['uat.viewer@sevenways.test', 'UAT Viewer', 'uat_viewer', 'UAT-CAI', 'UAT-CAI'],
        'disabled' => ['uat.disabled@sevenways.test', 'UAT Disabled User', 'uat_viewer', 'UAT-CAI', 'UAT-CAI'],
    ];

    public function run(): void
    {
        app(UatEnvironmentGuard::class)->assertSafe();

        $egp = Currency::query()->where('code', 'EGP')->where('is_active', true)->first();
        if (! $egp) {
            throw new RuntimeException('Active EGP is required. Run ProductionReferenceSeeder first.');
        }

        [$company, $branches, $users] = DB::transaction(
            fn (): array => $this->seedTenant($egp)
        );

        // Reference seeders are idempotent. The second pass attaches all module
        // permissions and mappings after the isolated UAT tenant and actor exist.
        app(ProductionReferenceSeeder::class)->run();

        DB::transaction(function () use ($company, $branches, $users, $egp): void {
            $this->syncRestrictedRoles($company);
            app(AccountantCashSessionPermissionReconciler::class)->reconcile();
            $this->seedFiscalPeriod($company, $users['owner']);
            $this->seedWarehouses($company, $branches);
            $this->seedInventorySequences($company, $branches);
            $products = $this->seedProducts($company, $users['owner']);
            $this->seedBranchProducts($company, $branches, $products, $users['owner']);
            $services = $this->seedServices($company, $branches, $users['owner']);
            $customers = $this->seedCustomers($company, $branches, $users['owner']);
            $this->seedVehicles($company, $branches['UAT-CAI'], $customers, $users['owner']);
            $this->seedSuppliers($company, $egp, $users['owner']);
            $employees = $this->seedEmployees($company, $branches, $users);
            $this->seedTreasury($company, $branches, $users, $employees, $egp);

            unset($products, $services);
        });
    }

    private function seedTenant(Currency $egp): array
    {
        $company = Company::withTrashed()->firstOrNew(['name' => self::COMPANY_NAME]);
        $company->forceFill([
            'name' => self::COMPANY_NAME,
            'legal_name' => 'Seven Ways UAT Egypt — Test Data Only',
            'commercial_registration' => null,
            'tax_number' => null,
            'email' => 'uat.company@sevenways.test',
            'phone' => '+201000000000',
            'address' => 'عنوان تجريبي — القاهرة — مصر',
            'country_code' => 'EG',
            'currency_code' => 'EGP',
            'currency_id' => $egp->id,
            'timezone' => 'Africa/Cairo',
            'fiscal_year_start_month' => 1,
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'money_decimal_places' => 2,
            'default_language' => 'ar',
            'ui_direction' => 'rtl',
            'is_active' => true,
            'deleted_at' => null,
        ])->save();

        $branches = collect([
            ['UAT-CAI', 'فرع القاهرة UAT', true, '+20200000001', 'القاهرة — عنوان تجريبي'],
            ['UAT-GIZ', 'فرع الجيزة UAT', false, '+20200000002', 'الجيزة — عنوان تجريبي'],
            ['UAT-ALX', 'فرع الإسكندرية UAT', false, '+20300000003', 'الإسكندرية — عنوان تجريبي'],
        ])->mapWithKeys(function (array $definition) use ($company): array {
            [$code, $name, $main, $phone, $address] = $definition;
            $branch = Branch::withTrashed()->firstOrNew([
                'company_id' => $company->id,
                'code' => $code,
            ]);
            $branch->forceFill([
                'company_id' => $company->id,
                'code' => $code,
                'name' => $name,
                'commercial_name' => $name,
                'email' => strtolower($code).'@sevenways.test',
                'phone' => $phone,
                'address' => $address,
                'is_main' => $main,
                'is_active' => true,
                'deleted_at' => null,
            ])->save();
            $branch->settings()->updateOrCreate([], [
                'invoice_prefix' => $code.'-INV',
                'quotation_prefix' => $code.'-QUO',
                'work_order_prefix' => $code.'-WO',
                'warranty_prefix' => $code.'-WAR',
            ]);

            return [$code => $branch];
        })->all();

        $roles = $this->seedRoles($company);
        $users = [];
        foreach (self::USERS as $key => [$email, $name, $roleName, $defaultCode, $access]) {
            $user = User::query()->where('email', $email)->firstOrNew();
            if ($user->exists && (int) $user->company_id !== (int) $company->id) {
                throw new RuntimeException("UAT email {$email} belongs to another company.");
            }
            $password = $user->exists && Hash::check(self::PASSWORD, (string) $user->password)
                ? $user->password
                : Hash::make(self::PASSWORD);
            $user->forceFill([
                'company_id' => $company->id,
                'branch_id' => $branches[$defaultCode]->id,
                'name' => $name,
                'email' => $email,
                'phone' => '+20100000'.str_pad((string) (array_search($key, array_keys(self::USERS), true) + 1), 4, '0', STR_PAD_LEFT),
                'password' => $password,
                'status' => $key === 'disabled' ? 'inactive' : 'active',
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
            $user->roles()->sync([$roles[$roleName]->id]);

            $codes = $access === '*' ? array_keys($branches) : explode(',', $access);
            $pivot = [];
            foreach ($codes as $code) {
                $pivot[$branches[$code]->id] = [
                    'is_default' => $code === $defaultCode,
                    'can_view' => true,
                    'can_create' => $roleName !== 'uat_viewer',
                    'can_update' => $roleName !== 'uat_viewer',
                    'can_approve' => in_array($roleName, [
                        'company_owner', 'general_manager', 'branch_manager', 'uat_treasury_manager',
                    ], true),
                ];
            }
            $user->accessibleBranches()->sync($pivot);
            $users[$key] = $user;
        }

        $taxes = [
            ['VAT14-EG', 'ضريبة القيمة المضافة المصرية 14%', 14, 'both', true],
            ['VAT0-EG', 'ضريبة قيمة مضافة صفرية', 0, 'both', false],
            ['EXEMPT-EG', 'معفى من ضريبة القيمة المضافة', 0, 'both', false],
        ];
        foreach ($taxes as [$code, $name, $rate, $type, $default]) {
            $tax = Tax::withTrashed()->firstOrNew(['company_id' => $company->id, 'code' => $code]);
            $tax->forceFill([
                'company_id' => $company->id,
                'code' => $code,
                'name' => $name,
                'rate' => $rate,
                'tax_type' => $type,
                'is_default' => $default,
                'is_inclusive' => false,
                'is_active' => true,
                'effective_from' => '2026-01-01',
                'effective_to' => null,
                'deleted_at' => null,
            ])->save();
            if ($default) {
                $company->forceFill(['default_tax_id' => $tax->id])->save();
            }
        }

        return [$company, $branches, $users];
    }

    private function seedRoles(Company $company): array
    {
        $definitions = [
            'company_owner' => ['مالك الشركة UAT', 'company'],
            'general_manager' => ['المدير العام UAT', 'company'],
            'branch_manager' => ['مدير فرع UAT', 'branch'],
            'accountant' => ['محاسب UAT', 'company'],
            'sales' => ['مبيعات UAT', 'branch'],
            'warehouse_keeper' => ['أمين مخزن UAT', 'branch'],
            'technician' => ['فني UAT', 'branch'],
            'quality_controller' => ['مراقب جودة UAT', 'branch'],
            'receptionist' => ['استقبال UAT', 'branch'],
            'uat_treasury_manager' => ['مدير خزينة UAT', 'company'],
            'uat_cashier' => ['أمين صندوق UAT', 'branch'],
            'uat_viewer' => ['عرض فقط UAT', 'branch'],
        ];

        return collect($definitions)->mapWithKeys(function (array $definition, string $name) use ($company): array {
            $role = Role::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                [
                    'display_name' => $definition[0],
                    'scope' => $definition[1],
                    'is_system' => false,
                    'is_active' => true,
                ]
            );

            return [$name => $role];
        })->all();
    }

    private function syncRestrictedRoles(Company $company): void
    {
        foreach ([
            'company_owner',
            'general_manager',
            'branch_manager',
            'accountant',
            'sales',
            'warehouse_keeper',
            'technician',
            'quality_controller',
            'receptionist',
        ] as $roleName) {
            $template = Role::query()->whereNull('company_id')->where('name', $roleName)->firstOrFail();
            Role::query()->where('company_id', $company->id)->where('name', $roleName)->firstOrFail()
                ->permissions()->syncWithoutDetaching($template->permissions()->pluck('permissions.id'));
        }

        $accountant = Role::query()->where('company_id', $company->id)
            ->where('name', 'accountant')->firstOrFail();
        $treasuryManager = Role::query()->where('company_id', $company->id)
            ->where('name', 'uat_treasury_manager')->firstOrFail();
        $cashier = Role::query()->where('company_id', $company->id)
            ->where('name', 'uat_cashier')->firstOrFail();
        $viewer = Role::query()->where('company_id', $company->id)
            ->where('name', 'uat_viewer')->firstOrFail();

        $treasuryManager->permissions()->sync(
            Permission::query()->where(fn ($query) => $query
                ->where('name', 'like', 'treasury.%')
                ->orWhereIn('name', ['dashboard.view', 'accounting.journals.view']))
                ->pluck('id')
        );
        $cashier->permissions()->sync(Permission::query()->whereIn('name', [
            'dashboard.view',
            'treasury.cash_boxes.view',
            'treasury.balances.view',
            'treasury.cash_sessions.view',
            'treasury.cash_sessions.open',
            'treasury.cash_sessions.count',
            'treasury.cash_sessions.submit',
            'treasury.cash_sessions.close',
            'treasury.cash_receipts.view',
            'treasury.cash_receipts.create',
            'treasury.cash_receipts.submit',
            'treasury.cash_payments.view',
            'treasury.cash_payments.create',
            'treasury.cash_payments.submit',
        ])->pluck('id'));
        $viewer->permissions()->sync(
            Permission::query()->where('name', 'dashboard.view')
                ->orWhere('name', 'like', '%.view')
                ->orWhere('name', 'like', '%.view_%')
                ->where('name', 'not like', '%.view_sensitive')
                ->where('name', 'not like', '%.view_cost')
                ->pluck('id')
        );
        $accountant->permissions()->detach(
            Permission::query()->where('name', 'like', '%.approve')->pluck('id')
        );
    }

    private function seedFiscalPeriod(Company $company, User $actor): void
    {
        $year = FiscalYear::query()->firstOrNew([
            'company_id' => $company->id,
            'start_date' => '2026-01-01',
        ]);
        $year->forceFill([
            'company_id' => $company->id,
            'name' => 'UAT 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
            'is_current' => true,
            'created_by' => $year->created_by ?: $actor->id,
        ])->save();
        $period = AccountingPeriod::query()->firstOrNew([
            'company_id' => $company->id,
            'fiscal_year_id' => $year->id,
            'period_number' => 7,
        ]);
        $period->forceFill([
            'company_id' => $company->id,
            'fiscal_year_id' => $year->id,
            'period_number' => 7,
            'code' => 'UAT-2026-07',
            'name' => 'UAT July 2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'open',
            'is_adjustment_period' => false,
            'locked_modules' => null,
            'closed_by' => null,
            'closed_at' => null,
        ])->save();
    }

    private function seedWarehouses(Company $company, array $branches): void
    {
        foreach ([
            ['UAT-CAI', 'UAT-CAI-MAIN', 'مخزن القاهرة الرئيسي UAT', 'main', true],
            ['UAT-CAI', 'UAT-CAI-INSTALL', 'مخزن تركيب القاهرة UAT', 'installation', false],
            ['UAT-GIZ', 'UAT-GIZ-MAIN', 'مخزن الجيزة الرئيسي UAT', 'main', true],
            ['UAT-ALX', 'UAT-ALX-MAIN', 'مخزن الإسكندرية الرئيسي UAT', 'main', true],
        ] as [$branchCode, $code, $name, $type, $main]) {
            $warehouse = Warehouse::withTrashed()->firstOrNew([
                'branch_id' => $branches[$branchCode]->id,
                'code' => $code,
            ]);
            $warehouse->forceFill([
                'company_id' => $company->id,
                'branch_id' => $branches[$branchCode]->id,
                'code' => $code,
                'name' => $name,
                'warehouse_type' => $type,
                'is_main' => $main,
                'is_active' => true,
                'is_system' => false,
                'allows_sale_issue' => true,
                'allows_work_order_issue' => true,
                'allows_damaged_stock' => false,
                'deleted_at' => null,
            ])->save();
        }
    }

    private function seedInventorySequences(Company $company, array $branches): void
    {
        $year = now()->format('Y');
        foreach ($branches as $branch) {
            foreach ([
                'product' => ['UAT-PRD-', 'never', null],
                'stock_movement' => ['{BRANCH}-STK-{YYYY}-', 'yearly', $year],
                'stock_opening' => ['{BRANCH}-OPEN-{YYYY}-', 'yearly', $year],
                'stock_adjustment' => ['{BRANCH}-ADJ-{YYYY}-', 'yearly', $year],
                'inventory_count' => ['{BRANCH}-COUNT-{YYYY}-', 'yearly', $year],
                'roll' => ['{BRANCH}-ROLL-', 'never', null],
                'roll_scrap' => ['{BRANCH}-SCRAP-', 'never', null],
            ] as $type => [$prefix, $resetPeriod, $periodKey]) {
                $sequence = DocumentSequence::query()->firstOrNew([
                    'scope_key' => DocumentNumberService::scopeKey(
                        $company->id,
                        $branch->id,
                        $type,
                        $periodKey
                    ),
                ]);
                $sequence->forceFill([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'document_type' => $type,
                    'prefix' => $prefix,
                    'current_number' => $sequence->exists ? $sequence->current_number : 0,
                    'padding' => 6,
                    'reset_period' => $resetPeriod,
                    'period_key' => $periodKey,
                    'is_active' => true,
                ])->save();
            }
        }
    }

    private function seedProducts(Company $company, User $actor): array
    {
        $category = ProductCategory::withTrashed()->firstOrNew([
            'company_id' => $company->id,
            'code' => 'UAT-PRODUCTS',
        ]);
        $category->forceFill([
            'company_id' => $company->id,
            'code' => 'UAT-PRODUCTS',
            'name' => 'منتجات UAT',
            'sort_order' => 1,
            'is_active' => true,
            'created_by' => $category->created_by ?: $actor->id,
            'deleted_at' => null,
        ])->save();
        $piece = Unit::query()->whereNull('company_id')->where('code', 'piece')->firstOrFail();
        $roll = Unit::query()->whereNull('company_id')->where('code', 'roll')->firstOrFail();
        $meter = Unit::query()->whereNull('company_id')->where('code', 'meter')->firstOrFail();
        $vat = Tax::query()->where('company_id', $company->id)->where('code', 'VAT14-EG')->firstOrFail();
        $exempt = Tax::query()->where('company_id', $company->id)->where('code', 'EXEMPT-EG')->firstOrFail();

        $definitions = [
            ['UAT-PROTECT-CLEAR', 'فيلم حماية شفاف UAT', 'film', $roll, $meter, $vat, 350, 700, 3, false],
            ['UAT-PROTECT-MATTE', 'فيلم حماية مطفي UAT', 'film', $roll, $meter, $vat, 400, 800, 3, false],
            ['UAT-HEAT-FILM', 'عازل حراري UAT', 'film', $roll, $meter, $vat, 250, 550, 2, false],
            ['UAT-INSTALL-KIT', 'إكسسوارات تركيب UAT', 'stock', $piece, $piece, $vat, 75, 150, 10, false],
            ['UAT-CONSUMABLE', 'مواد استهلاكية UAT', 'stock', $piece, $piece, $exempt, 20, 45, 20, true],
            ['CLEANER-PPF-UAT-001', 'منظف أفلام الحماية PPF UAT', 'stock', $piece, $piece, $vat, 100, 250, 5, true],
        ];

        $products = [];
        foreach ($definitions as [$sku, $name, $type, $purchaseUnit, $stockUnit, $tax, $cost, $price, $minimum, $consumable]) {
            $product = Product::withTrashed()->firstOrNew(['company_id' => $company->id, 'sku' => $sku]);
            $product->forceFill([
                'company_id' => $company->id,
                'category_id' => $category->id,
                'sku' => $sku,
                'name' => $name,
                'product_type' => $type,
                'tracking_type' => $type === 'film' ? 'roll' : 'quantity',
                'purchase_unit_id' => $purchaseUnit->id,
                'stock_unit_id' => $stockUnit->id,
                'sale_unit_id' => $stockUnit->id,
                'default_tax_id' => $tax->id,
                'costing_method' => 'weighted_average',
                'standard_cost' => $cost,
                'default_sale_price' => $price,
                'minimum_stock' => $minimum,
                'reorder_quantity' => $minimum * 2,
                'is_sellable' => true,
                'is_purchasable' => true,
                'is_consumable' => $consumable,
                'is_active' => true,
                'created_by' => $product->created_by ?: $actor->id,
                'deleted_at' => null,
            ])->save();
            $products[$sku] = $product;
        }

        return $products;
    }

    private function seedBranchProducts(Company $company, array $branches, array $products, User $actor): void
    {
        foreach ($branches as $branch) {
            foreach ($products as $product) {
                BranchProduct::query()->updateOrCreate([
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                ], [
                    'company_id' => $company->id,
                    'is_available' => true,
                    'is_sellable' => true,
                    'minimum_stock' => $product->minimum_stock,
                    'maximum_stock' => $product->maximum_stock,
                    'reorder_quantity' => $product->reorder_quantity,
                    'updated_by' => $actor->id,
                    'created_by' => $actor->id,
                ]);
            }
        }
    }

    private function seedServices(Company $company, array $branches, User $actor): array
    {
        $category = ServiceCategory::withTrashed()->firstOrNew([
            'company_id' => $company->id,
            'code' => 'UAT-INSTALLATION',
        ]);
        $category->forceFill([
            'company_id' => $company->id,
            'code' => 'UAT-INSTALLATION',
            'name' => 'خدمات تركيب UAT',
            'sort_order' => 1,
            'is_active' => true,
            'created_by' => $category->created_by ?: $actor->id,
            'deleted_at' => null,
        ])->save();
        $vat = Tax::query()->where('company_id', $company->id)->where('code', 'VAT14-EG')->firstOrFail();
        $unit = Unit::query()->whereNull('company_id')->where('code', 'piece')->firstOrFail();
        $definitions = [
            ['UAT-SVC-FULL-PROTECTION', 'تركيب فيلم حماية كامل UAT', 360, 6000, true],
            ['UAT-SVC-PARTIAL', 'تركيب حماية جزئية UAT', 180, 2500, true],
            ['UAT-SVC-HEAT', 'تركيب عازل حراري UAT', 240, 3500, true],
            ['UAT-SVC-REMOVAL', 'إزالة فيلم قديم UAT', 120, 750, false],
            ['UAT-SVC-WARRANTY', 'خدمة صيانة وضمان UAT', 90, 0, true],
        ];

        $services = [];
        foreach ($definitions as [$code, $name, $duration, $price, $quality]) {
            $service = Service::withTrashed()->firstOrNew(['company_id' => $company->id, 'code' => $code]);
            $service->forceFill([
                'company_id' => $company->id,
                'service_category_id' => $category->id,
                'code' => $code,
                'name' => $name,
                'service_type' => 'installation',
                'pricing_type' => 'fixed',
                'default_duration_minutes' => $duration,
                'default_tax_id' => $vat->id,
                'pricing_unit_id' => $unit->id,
                'default_warranty_months' => $quality ? 12 : null,
                'requires_vehicle' => true,
                'requires_inspection' => true,
                'requires_quality_check' => $quality,
                'allows_multiple_technicians' => true,
                'is_package_only' => false,
                'is_active' => true,
                'created_by' => $service->created_by ?: $actor->id,
                'deleted_at' => null,
            ])->save();
            foreach ($branches as $branch) {
                $availability = BranchService::query()->firstOrNew([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'service_id' => $service->id,
                ]);
                $availability->forceFill([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'service_id' => $service->id,
                    'is_available' => true,
                    'booking_enabled' => true,
                    'requires_approval' => false,
                    'default_duration_minutes' => $duration,
                    'default_price' => $price,
                    'minimum_price' => 0,
                    'maximum_discount_percentage' => 10,
                    'is_active' => true,
                ])->save();
                $servicePrice = ServicePrice::withTrashed()->firstOrNew([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'service_id' => $service->id,
                    'effective_from' => '2026-01-01',
                    'vehicle_size_id' => null,
                    'vehicle_type_id' => null,
                    'unit_id' => null,
                ]);
                $servicePrice->forceFill([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'service_id' => $service->id,
                    'effective_from' => '2026-01-01',
                    'vehicle_size_id' => null,
                    'vehicle_type_id' => null,
                    'unit_id' => null,
                    'price' => $price,
                    'minimum_price' => 0,
                    'estimated_duration_minutes' => $duration,
                    'effective_to' => null,
                    'priority' => 1,
                    'is_active' => true,
                    'deleted_at' => null,
                ])->save();
            }
            $services[$code] = $service;
        }

        return $services;
    }

    private function seedCustomers(Company $company, array $branches, User $actor): array
    {
        $definitions = [
            ['UAT-CUS-CASH', 'عميل نقدي UAT', 'individual', 'UAT-CAI', 0, 0, null],
            ['UAT-CUS-CREDIT', 'عميل آجل UAT', 'individual', 'UAT-CAI', 50000, 30, null],
            ['UAT-CUS-COMPANY', 'شركة عميل UAT', 'company', 'UAT-GIZ', 100000, 45, 'UAT-TAX-COMPANY'],
            ['UAT-CUS-LIMIT', 'عميل حد ائتماني UAT', 'individual', 'UAT-GIZ', 25000, 15, null],
            ['UAT-CUS-EXEMPT', 'عميل معفى UAT', 'individual', 'UAT-ALX', 0, 0, null],
            ['UAT-CUS-TAXABLE', 'عميل خاضع للضريبة UAT', 'individual', 'UAT-CAI', 0, 0, 'UAT-TAX-PERSON'],
        ];
        $customers = [];
        foreach ($definitions as $index => [$code, $name, $type, $branchCode, $limit, $terms, $taxNumber]) {
            $customer = Customer::withTrashed()->firstOrNew([
                'company_id' => $company->id,
                'customer_code' => $code,
            ]);
            $phone = '+20100001'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $customer->forceFill([
                'company_id' => $company->id,
                'created_branch_id' => $branches[$branchCode]->id,
                'assigned_branch_id' => $branches[$branchCode]->id,
                'customer_code' => $code,
                'customer_type' => $type,
                'name' => $name,
                'company_name' => $type === 'company' ? $name : null,
                'phone' => $phone,
                'normalized_phone' => preg_replace('/\D+/', '', $phone),
                'email' => strtolower($code).'@sevenways.test',
                'tax_number' => $taxNumber,
                'preferred_language' => 'ar',
                'credit_limit' => $limit,
                'payment_term_days' => $terms,
                'status' => 'active',
                'created_by' => $customer->created_by ?: $actor->id,
                'deleted_at' => null,
            ])->save();
            $customers[$code] = $customer;
        }

        return $customers;
    }

    private function seedVehicles(
        Company $company,
        Branch $branch,
        array $customers,
        User $actor
    ): void {
        $brand = VehicleBrand::query()->firstOrCreate(
            ['name_en' => 'UAT Test Motors'],
            ['name_ar' => 'علامة سيارات تجريبية UAT', 'country_code' => 'EG', 'is_active' => true]
        );
        $model = VehicleModel::query()->firstOrCreate(
            ['vehicle_brand_id' => $brand->id, 'name_en' => 'UAT Model'],
            ['name_ar' => 'موديل تجريبي UAT', 'start_year' => 2020, 'is_active' => true]
        );
        $types = VehicleType::query()->whereNull('company_id')
            ->whereIn('code', ['sedan', 'suv', 'pickup', 'hatchback', 'luxury'])->get()->keyBy('code');
        $sizes = VehicleSize::query()->whereNull('company_id')->get()->keyBy('code');

        foreach (['sedan', 'suv', 'pickup', 'hatchback', 'luxury'] as $index => $typeCode) {
            $customer = collect($customers)->values()->get($index);
            $vehicle = Vehicle::withTrashed()->firstOrNew([
                'company_id' => $company->id,
                'vin' => 'UATVIN'.str_pad((string) ($index + 1), 11, '0', STR_PAD_LEFT),
            ]);
            $plate = 'UAT-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $vehicle->forceFill([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'created_branch_id' => $branch->id,
                'vehicle_brand_id' => $brand->id,
                'vehicle_model_id' => $model->id,
                'vehicle_type_id' => $types[$typeCode]?->id,
                'vehicle_size_id' => $sizes[$typeCode === 'suv' ? 'suv' : ($typeCode === 'luxury' ? 'luxury' : 'medium')]?->id,
                'manufacturing_year' => 2024,
                'color' => 'UAT Test Color',
                'plate_number' => $plate,
                'normalized_plate_number' => $plate,
                'vin' => 'UATVIN'.str_pad((string) ($index + 1), 11, '0', STR_PAD_LEFT),
                'status' => 'active',
                'created_by' => $vehicle->created_by ?: $actor->id,
                'deleted_at' => null,
            ])->save();
        }
    }

    private function seedSuppliers(Company $company, Currency $egp, User $actor): void
    {
        foreach ([
            ['UAT-SUP-PPF', 'مورد أفلام حماية UAT', 'manufacturer', 30],
            ['UAT-SUP-HEAT', 'مورد عازل حراري UAT', 'manufacturer', 30],
            ['UAT-SUP-INSTALL', 'مورد مواد تركيب UAT', 'distributor', 15],
            ['UAT-SUP-CASH', 'مورد نقدي UAT', 'other', 0],
            ['UAT-SUP-CREDIT', 'مورد آجل UAT', 'other', 45],
        ] as $index => [$code, $name, $type, $terms]) {
            $supplier = Supplier::withTrashed()->firstOrNew([
                'company_id' => $company->id,
                'supplier_code' => $code,
            ]);
            $supplier->forceFill([
                'company_id' => $company->id,
                'supplier_code' => $code,
                'name' => $name,
                'legal_name' => $name,
                'supplier_type' => $type,
                'email' => strtolower($code).'@sevenways.test',
                'phone' => '+20100002'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'currency_id' => $egp->id,
                'payment_terms_days' => $terms,
                'credit_limit' => $terms > 0 ? 100000 : 0,
                'status' => 'active',
                'rating' => 4,
                'notes' => 'UAT test data only',
                'created_by' => $supplier->created_by ?: $actor->id,
                'deleted_at' => null,
            ])->save();
        }
    }

    private function seedEmployees(Company $company, array $branches, array $users): array
    {
        $definitions = [
            ['UAT-EMP-SALES-CAI', 'موظف مبيعات القاهرة UAT', 'UAT-CAI', 'sales', 'Sales'],
            ['UAT-EMP-TECH-CAI', 'فني تركيب القاهرة UAT', 'UAT-CAI', 'technician', 'Technician'],
            ['UAT-EMP-MGR-CAI', 'مدير فرع القاهرة UAT', 'UAT-CAI', 'cairo_manager', 'Branch Manager'],
            ['UAT-EMP-ACCOUNTANT', 'محاسب UAT', 'UAT-CAI', 'accountant', 'Accountant'],
            ['UAT-EMP-WH', 'أمين مخزن UAT', 'UAT-CAI', 'warehouse', 'Warehouse Keeper'],
            ['UAT-EMP-CASHIER', 'أمين صندوق UAT', 'UAT-CAI', 'cairo_cashier', 'Cashier'],
            ['UAT-EMP-QUALITY', 'مسؤول جودة UAT', 'UAT-CAI', 'quality', 'Quality Controller'],
            ['UAT-EMP-RECEPTION', 'موظف استقبال UAT', 'UAT-CAI', 'reception', 'Reception'],
            ['UAT-EMP-SALES-GIZ', 'موظف مبيعات الجيزة UAT', 'UAT-GIZ', null, 'Sales'],
            ['UAT-EMP-TECH-GIZ', 'فني الجيزة UAT', 'UAT-GIZ', null, 'Technician'],
        ];
        $employees = [];
        foreach ($definitions as $index => [$code, $name, $branchCode, $userKey, $title]) {
            $employee = Employee::withTrashed()->firstOrNew([
                'company_id' => $company->id,
                'employee_code' => $code,
            ]);
            $employee->forceFill([
                'company_id' => $company->id,
                'branch_id' => $branches[$branchCode]->id,
                'user_id' => $userKey ? $users[$userKey]->id : null,
                'employee_code' => $code,
                'name' => $name,
                'phone' => '+20100003'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'email' => strtolower($code).'@sevenways.test',
                'job_title' => $title,
                'employment_type' => 'full_time',
                'hire_date' => '2026-01-01',
                'status' => 'active',
                'deleted_at' => null,
            ])->save();
            $employees[$code] = $employee;
        }

        return $employees;
    }

    private function seedTreasury(
        Company $company,
        array $branches,
        array $users,
        array $employees,
        Currency $egp
    ): void {
        $accounts = [
            'cash_cairo' => $this->account($company, $users['owner'], 'UAT-CASH-CAI', 'نقدية القاهرة UAT', '111000', '111', 'debit', true),
            'cash_giza' => $this->account($company, $users['owner'], 'UAT-CASH-GIZ', 'نقدية الجيزة UAT', '111000', '111', 'debit', true),
            'cash_alx' => $this->account($company, $users['owner'], 'UAT-CASH-ALX', 'نقدية الإسكندرية UAT', '111000', '111', 'debit', true),
            'bank_cairo' => $this->account($company, $users['owner'], 'UAT-BANK-CAI', 'بنك القاهرة UAT', '112000', '111', 'debit', false, true),
            'bank_giza' => $this->account($company, $users['owner'], 'UAT-BANK-GIZ', 'بنك الجيزة UAT', '112000', '111', 'debit', false, true),
            'over_short' => $this->account($company, $users['owner'], 'UAT-CASH-OS', 'فروق الصندوق UAT', '600000', '600', 'debit'),
            'bank_fees' => $this->account($company, $users['owner'], 'UAT-BANK-FEES', 'رسوم بنكية UAT', '600000', '600', 'debit'),
        ];
        $boxes = [];
        foreach ([
            ['UAT-CAI', 'UAT-CAI-CASH', 'صندوق القاهرة UAT', 'cash_cairo', true],
            ['UAT-GIZ', 'UAT-GIZ-CASH', 'صندوق الجيزة UAT', 'cash_giza', false],
            ['UAT-ALX', 'UAT-ALX-CASH', 'صندوق الإسكندرية UAT', 'cash_alx', false],
        ] as [$branchCode, $code, $name, $accountKey, $shift]) {
            $box = CashBox::withTrashed()->firstOrNew(['company_id' => $company->id, 'code' => $code]);
            $box->forceFill([
                'company_id' => $company->id,
                'branch_id' => $branches[$branchCode]->id,
                'code' => $code,
                'name' => $name,
                'currency_id' => $egp->id,
                'gl_account_id' => $accounts[$accountKey]->id,
                'over_short_account_id' => $accounts['over_short']->id,
                'status' => 'active',
                'is_primary' => true,
                'allows_receipts' => true,
                'allows_payments' => true,
                'requires_shift_opening' => $shift,
                'created_by' => $box->created_by ?: $users['owner']->id,
                'deleted_at' => null,
            ])->save();
            $boxes[$branchCode] = $box;
        }
        $custodian = CashBoxCustodian::query()->firstOrNew([
            'company_id' => $company->id,
            'cash_box_id' => $boxes['UAT-CAI']->id,
            'user_id' => $users['cairo_cashier']->id,
            'valid_from' => '2026-01-01',
        ]);
        $custodian->forceFill([
            'company_id' => $company->id,
            'cash_box_id' => $boxes['UAT-CAI']->id,
            'user_id' => $users['cairo_cashier']->id,
            'employee_id' => $employees['UAT-EMP-CASHIER']->id,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
            'can_receive' => true,
            'can_pay' => true,
            'can_transfer' => false,
            'payment_limit' => 5000,
            'is_primary' => true,
            'is_active' => true,
            'assigned_by' => $custodian->assigned_by ?: $users['owner']->id,
        ])->save();

        $bank = Bank::withTrashed()->firstOrNew(['scope_key' => $company->id.':UAT-EGYPT-BANK']);
        $bank->forceFill([
            'company_id' => $company->id,
            'scope_key' => $company->id.':UAT-EGYPT-BANK',
            'code' => 'UAT-EGYPT-BANK',
            'name_ar' => 'بنك مصر التجريبي UAT',
            'name_en' => 'UAT Egypt Test Bank',
            'swift_code' => null,
            'is_system' => false,
            'is_active' => true,
            'deleted_at' => null,
        ])->save();
        foreach ([
            ['UAT-CAI', 'UAT-BANK-CAI', 'حساب بنك القاهرة UAT', 'UAT-****-1001', 'bank_cairo'],
            ['UAT-GIZ', 'UAT-BANK-GIZ', 'حساب بنك الجيزة UAT', 'UAT-****-2001', 'bank_giza'],
        ] as [$branchCode, $code, $name, $masked, $accountKey]) {
            $bankAccount = BankAccount::withTrashed()->firstOrNew([
                'company_id' => $company->id,
                'account_code' => $code,
            ]);
            $bankAccount->forceFill([
                'company_id' => $company->id,
                'bank_id' => $bank->id,
                'branch_id' => $branches[$branchCode]->id,
                'account_code' => $code,
                'account_name' => $name,
                'iban' => null,
                'iban_hash' => null,
                'account_number_masked' => $masked,
                'currency_id' => $egp->id,
                'gl_account_id' => $accounts[$accountKey]->id,
                'bank_fees_account_id' => $accounts['bank_fees']->id,
                'status' => 'active',
                'account_type' => 'current',
                'opening_date' => '2026-01-01',
                'is_primary' => true,
                'allows_receipts' => true,
                'allows_payments' => true,
                'allows_transfers' => true,
                'requires_reconciliation' => true,
                'created_by' => $bankAccount->created_by ?: $users['owner']->id,
                'deleted_at' => null,
            ])->save();
            $access = BankAccountBranchAccess::query()->firstOrNew([
                'bank_account_id' => $bankAccount->id,
                'branch_id' => $branches[$branchCode]->id,
            ]);
            $access->forceFill([
                'company_id' => $company->id,
                'bank_account_id' => $bankAccount->id,
                'branch_id' => $branches[$branchCode]->id,
                'can_view' => true,
                'can_receive' => true,
                'can_pay' => true,
                'can_transfer' => true,
                'daily_payment_limit' => 100000,
                'daily_transfer_limit' => 100000,
                'is_active' => true,
            ])->save();
        }
    }

    private function account(
        Company $company,
        User $actor,
        string $code,
        string $name,
        string $parentCode,
        string $groupCode,
        string $normalBalance,
        bool $cash = false,
        bool $bank = false
    ): Account {
        $parent = Account::query()->where('company_id', $company->id)
            ->where('account_code', $parentCode)->firstOrFail();
        $group = AccountGroup::query()->where('company_id', $company->id)
            ->where('code', $groupCode)->firstOrFail();
        $account = Account::withTrashed()->firstOrNew([
            'company_id' => $company->id,
            'account_code' => $code,
        ]);
        $account->forceFill([
            'company_id' => $company->id,
            'account_code' => $code,
            'account_type_id' => $group->account_type_id,
            'account_group_id' => $group->id,
            'parent_account_id' => $parent->id,
            'name_ar' => $name,
            'account_level' => $parent->account_level + 1,
            'normal_balance' => $normalBalance,
            'currency_id' => $company->currency_id,
            'is_header' => false,
            'is_posting' => true,
            'requires_branch' => $cash || $bank,
            'is_control_account' => false,
            'is_bank_account' => $bank,
            'is_cash_account' => $cash,
            'is_inventory_account' => false,
            'is_tax_account' => false,
            'is_system' => false,
            'is_active' => true,
            'allow_manual_entry' => false,
            'created_by' => $account->created_by ?: $actor->id,
            'deleted_at' => null,
        ])->save();
        $account->forceFill(['account_path' => $parent->account_path.'/'.$account->id])->saveQuietly();

        return $account;
    }
}
