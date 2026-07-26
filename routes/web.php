<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountGroupController;
use App\Http\Controllers\AccountingDashboardController;
use App\Http\Controllers\AccountingMappingController;
use App\Http\Controllers\AccountingPeriodController;
use App\Http\Controllers\AccountingPostingController;
use App\Http\Controllers\AccountingReconciliationController;
use App\Http\Controllers\AccountingSettingsController;
use App\Http\Controllers\AccountTypeController;
use App\Http\Controllers\AppointmentActionController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BalanceSheetController;
use App\Http\Controllers\BranchContextController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchFinancialReportController;
use App\Http\Controllers\BranchSettingsController;
use App\Http\Controllers\CashFlowController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CostCenterController;
use App\Http\Controllers\CostCenterFinancialReportController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPaymentController;
use App\Http\Controllers\CustomerRefundController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\FinancialReportDefinitionController;
use App\Http\Controllers\FiscalYearController;
use App\Http\Controllers\GeneralJournalInquiryController;
use App\Http\Controllers\GeneralLedgerController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\IncomeStatementController;
use App\Http\Controllers\InventoryActionController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryDocumentController;
use App\Http\Controllers\JournalEntryActionController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\OpeningBalanceController;
use App\Http\Controllers\OpeningBalancePostingController;
use App\Http\Controllers\PostingProfileController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductReferenceController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequisitionController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\PurchasingReportController;
use App\Http\Controllers\QualityCheckController;
use App\Http\Controllers\QualityChecklistController;
use App\Http\Controllers\QuotationActionController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReferenceDataController;
use App\Http\Controllers\ReworkOrderController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesCreditNoteController;
use App\Http\Controllers\SalesInvoiceController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServicePackageController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierCreditNoteController;
use App\Http\Controllers\SupplierInvoiceController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\TrialBalanceController;
use App\Http\Controllers\UnpostedAccountingSourceController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleInspectionController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarrantyClaimController;
use App\Http\Controllers\WarrantyController;
use App\Http\Controllers\WarrantyVerificationController;
use App\Http\Controllers\Website\ContactController as WebsiteContactController;
use App\Http\Controllers\Website\WebsiteController;
use App\Http\Controllers\WorkOrderActionController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\WorkOrderMaterialController;
use App\Http\Middleware\SetWebsiteLocale;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/*
|--------------------------------------------------------------------------
| Public Website Routes
|--------------------------------------------------------------------------
*/

Route::middleware(SetWebsiteLocale::class)->name('website.')->group(function () {
    Route::get('/', [WebsiteController::class, 'home'])->name('home');
    Route::get('about-us', [WebsiteController::class, 'about'])->name('about');
    Route::get('our-services', [WebsiteController::class, 'services'])->name('services');
    Route::get('contact-us', [WebsiteController::class, 'contact'])->name('contact');
    Route::post('contact-us', [WebsiteContactController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('contact.submit');
    Route::get('sitemap.xml', [WebsiteController::class, 'sitemap'])->name('sitemap');
});

Route::post('website/language/{locale}', [WebsiteController::class, 'language'])
    ->whereIn('locale', ['ar', 'en'])
    ->name('website.language');

/*
|--------------------------------------------------------------------------
| Authentication and Accounting System Routes
|--------------------------------------------------------------------------
*/

Route::get('warranty/verify/{token}', WarrantyVerificationController::class)
    ->middleware('throttle:30,1')
    ->where('token', '[A-Za-z0-9]{40,96}')
    ->name('warranties.verify');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware(['auth', 'active.user', 'tenant'])->group(function () {
    Route::get('dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');

    Route::prefix('accounting')->name('accounting.')->group(function () {
        Route::get('/', AccountingDashboardController::class)->middleware('permission:accounting.accounts.view')->name('dashboard');
        Route::get('accounts', [AccountController::class, 'index'])->middleware('permission:accounting.accounts.view')->name('accounts.index');
        Route::get('accounts/create', [AccountController::class, 'create'])->middleware('permission:accounting.accounts.create')->name('accounts.create');
        Route::post('accounts', [AccountController::class, 'store'])->middleware('permission:accounting.accounts.create')->name('accounts.store');
        Route::get('accounts/{account}/edit', [AccountController::class, 'edit'])->middleware('permission:accounting.accounts.update')->name('accounts.edit');
        Route::put('accounts/{account}', [AccountController::class, 'update'])->middleware('permission:accounting.accounts.update')->name('accounts.update');
        Route::post('accounts/{account}/move', [AccountController::class, 'move'])->middleware('permission:accounting.accounts.move')->name('accounts.move');
        Route::post('accounts/{account}/disable', [AccountController::class, 'disable'])->middleware('permission:accounting.accounts.disable')->name('accounts.disable');

        Route::get('account-groups', [AccountGroupController::class, 'index'])->middleware('permission:accounting.account_groups.view')->name('groups.index');
        Route::post('account-groups', [AccountGroupController::class, 'store'])->middleware('permission:accounting.account_groups.create')->name('groups.store');
        Route::put('account-groups/{accountGroup}', [AccountGroupController::class, 'update'])->middleware('permission:accounting.account_groups.update')->name('groups.update');
        Route::post('account-groups/{accountGroup}/disable', [AccountGroupController::class, 'disable'])->middleware('permission:accounting.account_groups.disable')->name('groups.disable');
        Route::get('account-types', [AccountTypeController::class, 'index'])->middleware('permission:accounting.account_types.view')->name('account-types.index');
        Route::post('account-types', [AccountTypeController::class, 'store'])->middleware('permission:accounting.account_types.manage')->name('account-types.store');
        Route::put('account-types/{accountType}', [AccountTypeController::class, 'update'])->middleware('permission:accounting.account_types.manage')->name('account-types.update');

        Route::get('fiscal-years', [FiscalYearController::class, 'index'])->middleware('permission:accounting.fiscal_years.view')->name('fiscal-years.index');
        Route::post('fiscal-years', [FiscalYearController::class, 'store'])->middleware('permission:accounting.fiscal_years.create')->name('fiscal-years.store');
        Route::post('fiscal-years/{fiscalYear}/generate', [FiscalYearController::class, 'generate'])->middleware('permission:accounting.periods.create')->name('fiscal-years.generate');
        Route::post('fiscal-years/{fiscalYear}/{action}', [FiscalYearController::class, 'action'])->whereIn('action', ['open', 'soft_close', 'reopen'])->name('fiscal-years.action');

        Route::get('periods', [AccountingPeriodController::class, 'index'])->middleware('permission:accounting.periods.view')->name('periods.index');
        Route::post('periods', [AccountingPeriodController::class, 'store'])->middleware('permission:accounting.periods.create')->name('periods.store');
        Route::post('periods/{accountingPeriod}/{action}', [AccountingPeriodController::class, 'action'])->whereIn('action', ['open', 'soft_close', 'reopen', 'lock'])->name('periods.action');

        Route::get('cost-centers', [CostCenterController::class, 'index'])->middleware('permission:accounting.cost_centers.view')->name('cost-centers.index');
        Route::post('cost-centers', [CostCenterController::class, 'store'])->middleware('permission:accounting.cost_centers.create')->name('cost-centers.store');
        Route::put('cost-centers/{costCenter}', [CostCenterController::class, 'update'])->middleware('permission:accounting.cost_centers.update')->name('cost-centers.update');
        Route::post('cost-centers/{costCenter}/move', [CostCenterController::class, 'move'])->middleware('permission:accounting.cost_centers.move')->name('cost-centers.move');
        Route::post('cost-centers/{costCenter}/disable', [CostCenterController::class, 'disable'])->middleware('permission:accounting.cost_centers.disable')->name('cost-centers.disable');

        Route::get('settings', [AccountingSettingsController::class, 'edit'])->middleware('permission:accounting.settings.view')->name('settings.edit');
        Route::put('settings', [AccountingSettingsController::class, 'update'])->middleware('permission:accounting.settings.update')->name('settings.update');
        Route::put('branches/{branch}/settings', [AccountingSettingsController::class, 'branch'])->middleware('permission:accounting.branch_mappings.update')->name('branch-settings.update');

        Route::get('posting-profiles', [PostingProfileController::class, 'index'])->middleware('permission:accounting.posting_profiles.view')->name('posting-profiles.index');
        Route::post('posting-profiles', [PostingProfileController::class, 'store'])->middleware('permission:accounting.posting_profiles.create')->name('posting-profiles.store');
        Route::post('posting-profiles/{postingProfile}/{action}', [PostingProfileController::class, 'action'])->whereIn('action', ['activate', 'supersede'])->name('posting-profiles.action');

        Route::get('opening-balances', [OpeningBalanceController::class, 'index'])->middleware('permission:accounting.opening_balances.view')->name('opening-balances.index');
        Route::post('opening-balances', [OpeningBalanceController::class, 'store'])->middleware('permission:accounting.opening_balances.create')->name('opening-balances.store');
        Route::post('opening-balances/{openingBalance}/lines', [OpeningBalanceController::class, 'line'])->middleware('permission:accounting.opening_balances.update')->name('opening-balances.lines.store');
        Route::post('opening-balances/{openingBalance}/{action}', [OpeningBalanceController::class, 'action'])->whereIn('action', ['submit', 'approve', 'mark_ready'])->name('opening-balances.action');
        Route::post('opening-balances/{openingBalance}/post', [OpeningBalancePostingController::class, 'post'])->middleware('permission:accounting.opening_balances.post')->name('opening-balances.post');
        Route::post('opening-balances/{openingBalance}/reverse', [OpeningBalancePostingController::class, 'reverse'])->middleware('permission:accounting.opening_balances.reverse')->name('opening-balances.reverse');

        Route::get('journals', [JournalEntryController::class, 'index'])->middleware('permission:accounting.journals.view')->name('journals.index');
        Route::get('journals/create', [JournalEntryController::class, 'create'])->middleware('permission:accounting.journals.create')->name('journals.create');
        Route::post('journals', [JournalEntryController::class, 'store'])->middleware('permission:accounting.journals.create')->name('journals.store');
        Route::get('journals/{journalEntry}', [JournalEntryController::class, 'show'])->middleware('permission:accounting.journals.view')->name('journals.show');
        Route::get('journals/{journalEntry}/edit', [JournalEntryController::class, 'edit'])->middleware('permission:accounting.journals.update')->name('journals.edit');
        Route::put('journals/{journalEntry}', [JournalEntryController::class, 'update'])->middleware('permission:accounting.journals.update')->name('journals.update');
        Route::post('journals/{journalEntry}/{action}', [JournalEntryActionController::class, 'action'])->whereIn('action', ['submit', 'approve', 'post', 'cancel'])->name('journals.action');
        Route::post('journals/{journalEntry}/reverse', [JournalEntryActionController::class, 'reverse'])->middleware('permission:accounting.journals.reverse')->name('journals.reverse');

        Route::get('posting', [AccountingPostingController::class, 'index'])->middleware('permission:accounting.posting.execute')->name('posting.index');
        Route::post('posting/{sourceType}/{sourceUuid}/preview', [AccountingPostingController::class, 'preview'])->middleware('permission:accounting.posting.preview')->name('posting.preview');
        Route::post('posting/{sourceType}/{sourceUuid}', [AccountingPostingController::class, 'post'])->middleware('permission:accounting.posting.execute')->name('posting.post');
        Route::post('posting/{sourceType}/{sourceUuid}/reverse', [AccountingPostingController::class, 'reverse'])->middleware('permission:accounting.posting.reverse')->name('posting.reverse');

        Route::get('mappings', [AccountingMappingController::class, 'index'])->name('mappings.index');
        Route::post('mappings/payment-methods', [AccountingMappingController::class, 'paymentMethod'])->middleware('permission:accounting.mappings.payment_methods')->name('mappings.payment-methods');
        Route::post('mappings/products', [AccountingMappingController::class, 'product'])->middleware('permission:accounting.mappings.products')->name('mappings.products');

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('general-ledger', GeneralLedgerController::class)->middleware('permission:accounting.general_ledger.view')->name('general-ledger');
            Route::get('general-journal', GeneralJournalInquiryController::class)->middleware('permission:accounting.general_journal.view')->name('general-journal');
            Route::get('trial-balance', TrialBalanceController::class)->middleware('permission:accounting.trial_balance.view')->name('trial-balance');
            Route::get('income-statement', IncomeStatementController::class)->middleware('permission:accounting.income_statement.view')->name('income-statement');
            Route::get('balance-sheet', BalanceSheetController::class)->middleware('permission:accounting.balance_sheet.view')->name('balance-sheet');
            Route::get('cash-flow', CashFlowController::class)->middleware('permission:accounting.cash_flow.view')->name('cash-flow');
            Route::get('cost-centers', CostCenterFinancialReportController::class)->middleware('permission:accounting.cost_center_reports.view')->name('cost-centers');
            Route::get('branches', BranchFinancialReportController::class)->middleware('permission:accounting.branch_reports.view')->name('branches');
            Route::get('reconciliation', AccountingReconciliationController::class)->name('reconciliation');
            Route::get('unposted-sources', UnpostedAccountingSourceController::class)->middleware('permission:accounting.unposted_sources.view')->name('unposted-sources');
            Route::get('definitions', [FinancialReportDefinitionController::class, 'index'])->middleware('permission:accounting.financial_reports.manage_definitions')->name('definitions');
            Route::post('definitions', [FinancialReportDefinitionController::class, 'store'])->middleware('permission:accounting.financial_reports.manage_definitions')->name('definitions.store');
            Route::post('definitions/cash-flow-mappings', [FinancialReportDefinitionController::class, 'mapping'])->middleware('permission:accounting.financial_reports.manage_mappings')->name('definitions.mapping');
        });
    });
    Route::post('branch-context', [BranchContextController::class, 'store'])->name('branch-context.store');

    Route::get('settings/company', [CompanyController::class, 'edit'])->middleware('permission:companies.view')->name('company.edit');
    Route::put('settings/company/{company}', [CompanyController::class, 'update'])->middleware('permission:companies.update')->name('company.update');
    Route::get('settings/branch', [BranchSettingsController::class, 'edit'])->name('branch-settings.edit');
    Route::put('settings/branch', [BranchSettingsController::class, 'update'])->name('branch-settings.update');

    Route::get('settings/reference/{section}', [ReferenceDataController::class, 'index'])->name('reference.index');
    Route::get('settings/reference/{section}/create', [ReferenceDataController::class, 'create'])->name('reference.create');
    Route::post('settings/reference/{section}', [ReferenceDataController::class, 'store'])->name('reference.store');
    Route::get('settings/reference/{section}/{reference}/edit', [ReferenceDataController::class, 'edit'])->whereNumber('reference')->name('reference.edit');
    Route::put('settings/reference/{section}/{reference}', [ReferenceDataController::class, 'update'])->whereNumber('reference')->name('reference.update');

    Route::get('customers', [CustomerController::class, 'index'])->middleware('permission:customers.view')->name('customers.index');
    Route::get('customers/create', [CustomerController::class, 'create'])->middleware('permission:customers.create')->name('customers.create');
    Route::post('customers', [CustomerController::class, 'store'])->middleware('permission:customers.create')->name('customers.store');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view')->name('customers.show');
    Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->middleware('permission:customers.update')->name('customers.edit');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.update')->name('customers.update');
    Route::patch('customers/{customer}/disable', [CustomerController::class, 'disable'])->middleware('permission:customers.disable')->name('customers.disable');
    Route::post('customers/{customer}/contacts', [CustomerController::class, 'storeContact'])->name('customers.contacts.store');
    Route::delete('customer-contacts/{contact}', [CustomerController::class, 'destroyContact'])->middleware('permission:customers.manage_contacts')->name('customers.contacts.destroy');
    Route::post('customers/{customer}/addresses', [CustomerController::class, 'storeAddress'])->name('customers.addresses.store');
    Route::post('customers/{customer}/notes', [CustomerController::class, 'storeNote'])->name('customers.notes.store');
    Route::post('customers/{customer}/attachments', [AttachmentController::class, 'storeForCustomer'])->name('customers.attachments.store');

    Route::get('vehicles', [VehicleController::class, 'index'])->middleware('permission:vehicles.view')->name('vehicles.index');
    Route::get('vehicles/create', [VehicleController::class, 'create'])->middleware('permission:vehicles.create')->name('vehicles.create');
    Route::post('vehicles', [VehicleController::class, 'store'])->middleware('permission:vehicles.create')->name('vehicles.store');
    Route::get('vehicles/{vehicle}', [VehicleController::class, 'show'])->middleware('permission:vehicles.view')->name('vehicles.show');
    Route::get('vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])->middleware('permission:vehicles.update')->name('vehicles.edit');
    Route::put('vehicles/{vehicle}', [VehicleController::class, 'update'])->middleware('permission:vehicles.update')->name('vehicles.update');
    Route::post('vehicles/{vehicle}/transfer', [VehicleController::class, 'transfer'])->middleware('permission:vehicles.transfer_ownership')->name('vehicles.transfer');
    Route::post('vehicles/{vehicle}/attachments', [AttachmentController::class, 'storeForVehicle'])->name('vehicles.attachments.store');

    Route::get('leads', [LeadController::class, 'index'])->middleware('permission:leads.view')->name('leads.index');
    Route::get('leads/create', [LeadController::class, 'create'])->middleware('permission:leads.create')->name('leads.create');
    Route::post('leads', [LeadController::class, 'store'])->middleware('permission:leads.create')->name('leads.store');
    Route::get('leads/{lead}', [LeadController::class, 'show'])->middleware('permission:leads.view')->name('leads.show');
    Route::get('leads/{lead}/edit', [LeadController::class, 'edit'])->middleware('permission:leads.update')->name('leads.edit');
    Route::put('leads/{lead}', [LeadController::class, 'update'])->middleware('permission:leads.update')->name('leads.update');
    Route::post('leads/{lead}/follow-ups', [LeadController::class, 'followUp'])->middleware('permission:leads.follow_up')->name('leads.follow-ups.store');
    Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->middleware('permission:leads.convert')->name('leads.convert');
    Route::post('leads/{lead}/lost', [LeadController::class, 'lost'])->middleware('permission:leads.close')->name('leads.lost');

    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('branches', [BranchController::class, 'index'])->middleware('permission:branches.view')->name('branches.index');
    Route::get('branches/create', [BranchController::class, 'create'])->middleware('permission:branches.create')->name('branches.create');
    Route::post('branches', [BranchController::class, 'store'])->middleware('permission:branches.create')->name('branches.store');
    Route::get('branches/{branch}', [BranchController::class, 'show'])->middleware('permission:branches.view')->name('branches.show');
    Route::get('branches/{branch}/edit', [BranchController::class, 'edit'])->middleware('permission:branches.update')->name('branches.edit');
    Route::put('branches/{branch}', [BranchController::class, 'update'])->middleware('permission:branches.update')->name('branches.update');
    Route::patch('branches/{branch}/disable', [BranchController::class, 'disable'])->middleware('permission:branches.disable')->name('branches.disable');
    Route::patch('branches/{branch}/main', [BranchController::class, 'makeMain'])->middleware('permission:branches.update')->name('branches.main');

    Route::get('users', [UserManagementController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::get('users/create', [UserManagementController::class, 'create'])->middleware('permission:users.create')->name('users.create');
    Route::post('users', [UserManagementController::class, 'store'])->middleware('permission:users.create')->name('users.store');
    Route::get('users/{user}/edit', [UserManagementController::class, 'edit'])->middleware('permission:users.update')->name('users.edit');
    Route::put('users/{user}', [UserManagementController::class, 'update'])->middleware('permission:users.update')->name('users.update');
    Route::patch('users/{user}/disable', [UserManagementController::class, 'disable'])->middleware('permission:users.disable')->name('users.disable');

    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
    Route::get('roles/create', [RoleController::class, 'create'])->middleware('permission:roles.manage')->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.manage')->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.manage')->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage')->name('roles.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
    Route::get('products/create', [ProductController::class, 'create'])->middleware('permission:products.create')->name('products.create');
    Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->middleware('permission:products.update')->name('products.edit');
    Route::put('products/{product}', [ProductController::class, 'update'])->middleware('permission:products.update')->name('products.update');
    Route::patch('products/{product}/disable', [ProductController::class, 'disable'])->middleware('permission:products.disable')->name('products.disable');
    Route::get('product-references/{section}', [ProductReferenceController::class, 'index'])->whereIn('section', ['categories', 'brands'])->middleware('permission:products.view')->name('product-references.index');
    Route::post('product-references/{section}', [ProductReferenceController::class, 'store'])->whereIn('section', ['categories', 'brands'])->middleware('permission:products.view')->name('product-references.store');

    Route::get('warehouses', [WarehouseController::class, 'index'])->middleware('permission:warehouses.view')->name('warehouses.index');
    Route::get('warehouses/create', [WarehouseController::class, 'create'])->middleware('permission:warehouses.create')->name('warehouses.create');
    Route::post('warehouses', [WarehouseController::class, 'store'])->middleware('permission:warehouses.create')->name('warehouses.store');
    Route::patch('warehouses/{warehouse}/disable', [WarehouseController::class, 'disable'])->middleware('permission:warehouses.disable')->name('warehouses.disable');

    Route::get('inventory/{section}', InventoryController::class)
        ->whereIn('section', ['balances', 'movements', 'rolls', 'scraps', 'openings', 'adjustments', 'counts', 'reservations', 'alerts'])
        ->middleware('permission:inventory.view')->name('inventory.index');
    Route::get('inventory-documents/{section}/create', [InventoryDocumentController::class, 'create'])->whereIn('section', ['openings', 'adjustments', 'counts'])->middleware('permission:inventory.view')->name('inventory.documents.create');
    Route::post('inventory-documents/{section}', [InventoryDocumentController::class, 'store'])->whereIn('section', ['openings', 'adjustments', 'counts'])->middleware('permission:inventory.view')->name('inventory.documents.store');
    Route::post('inventory/rolls/{roll}/consume', [InventoryActionController::class, 'consumeRoll'])->middleware('permission:rolls.consume')->name('inventory.rolls.consume');
    Route::post('inventory/rolls/{roll}/scraps', [InventoryActionController::class, 'createScrap'])->middleware('permission:rolls.manage_scraps')->name('inventory.rolls.scraps.store');
    Route::post('inventory/scraps/{scrap}/consume', [InventoryActionController::class, 'consumeScrap'])->middleware('permission:rolls.manage_scraps')->name('inventory.scraps.consume');
    Route::post('inventory/movements/{movement}/reverse', [InventoryActionController::class, 'reverseMovement'])->middleware('permission:inventory.reverse')->name('inventory.movements.reverse');
    Route::post('inventory/openings/{opening}/post', [InventoryActionController::class, 'postOpening'])->middleware('permission:inventory.post')->name('inventory.openings.post');
    Route::post('inventory/adjustments/{adjustment}/post', [InventoryActionController::class, 'postAdjustment'])->middleware('permission:inventory.post')->name('inventory.adjustments.post');
    Route::post('inventory/counts/{count}/snapshot', [InventoryActionController::class, 'snapshotCount'])->middleware('permission:inventory.count')->name('inventory.counts.snapshot');
    Route::post('inventory/counts/{count}/post', [InventoryActionController::class, 'postCount'])->middleware('permission:inventory.post')->name('inventory.counts.post');
    Route::post('inventory/reservations/{reservation}/release', [InventoryActionController::class, 'releaseReservation'])->middleware('permission:inventory_reservations.manage')->name('inventory.reservations.release');

    Route::get('stock-transfers', [StockTransferController::class, 'index'])->middleware('permission:stock_transfers.view')->name('stock-transfers.index');
    Route::get('stock-transfers/create', [StockTransferController::class, 'create'])->middleware('permission:stock_transfers.create')->name('stock-transfers.create');
    Route::post('stock-transfers', [StockTransferController::class, 'store'])->middleware('permission:stock_transfers.create')->name('stock-transfers.store');
    Route::get('stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])->middleware('permission:stock_transfers.view')->name('stock-transfers.show');
    Route::get('stock-transfers/{stockTransfer}/edit', [StockTransferController::class, 'edit'])->middleware('permission:stock_transfers.update')->name('stock-transfers.edit');
    Route::put('stock-transfers/{stockTransfer}', [StockTransferController::class, 'update'])->middleware('permission:stock_transfers.update')->name('stock-transfers.update');
    Route::post('stock-transfers/{stockTransfer}/submit', [StockTransferController::class, 'submit'])->middleware('permission:stock_transfers.update')->name('stock-transfers.submit');
    Route::post('stock-transfers/{stockTransfer}/approval', [StockTransferController::class, 'approval'])->middleware('permission:stock_transfers.approve')->name('stock-transfers.approval');
    Route::post('stock-transfers/{stockTransfer}/prepare', [StockTransferController::class, 'prepare'])->middleware('permission:stock_transfers.prepare')->name('stock-transfers.prepare');
    Route::post('stock-transfers/{stockTransfer}/ship', [StockTransferController::class, 'ship'])->middleware('permission:stock_transfers.ship')->name('stock-transfers.ship');
    Route::post('stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive'])->middleware('permission:stock_transfers.receive')->name('stock-transfers.receive');
    Route::post('stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])->middleware('permission:stock_transfers.cancel')->name('stock-transfers.cancel');
    Route::post('stock-transfers/{stockTransfer}/reverse', [StockTransferController::class, 'reverse'])->middleware('permission:stock_transfers.reverse')->name('stock-transfers.reverse');
    Route::post('stock-transfers/{stockTransfer}/discrepancies', [StockTransferController::class, 'discrepancy'])->middleware('permission:stock_transfers.receive')->name('stock-transfers.discrepancies.store');
    Route::post('stock-transfer-discrepancies/{discrepancy}/resolve', [StockTransferController::class, 'resolve'])->middleware('permission:stock_transfers.resolve_discrepancy')->name('stock-transfers.discrepancies.resolve');

    Route::get('service-categories', [ServiceCategoryController::class, 'index'])->middleware('permission:service_categories.view')->name('service-categories.index');
    Route::get('service-categories/create', [ServiceCategoryController::class, 'create'])->middleware('permission:service_categories.manage')->name('service-categories.create');
    Route::post('service-categories', [ServiceCategoryController::class, 'store'])->middleware('permission:service_categories.manage')->name('service-categories.store');
    Route::get('service-categories/{serviceCategory}/edit', [ServiceCategoryController::class, 'edit'])->middleware('permission:service_categories.manage')->name('service-categories.edit');
    Route::put('service-categories/{serviceCategory}', [ServiceCategoryController::class, 'update'])->middleware('permission:service_categories.manage')->name('service-categories.update');
    Route::patch('service-categories/{serviceCategory}/disable', [ServiceCategoryController::class, 'disable'])->middleware('permission:service_categories.manage')->name('service-categories.disable');

    Route::get('services', [ServiceController::class, 'index'])->middleware('permission:services.view')->name('services.index');
    Route::get('services/create', [ServiceController::class, 'create'])->middleware('permission:services.create')->name('services.create');
    Route::post('services', [ServiceController::class, 'store'])->middleware('permission:services.create')->name('services.store');
    Route::get('services/{service}', [ServiceController::class, 'show'])->middleware('permission:services.view')->name('services.show');
    Route::get('services/{service}/edit', [ServiceController::class, 'edit'])->middleware('permission:services.update')->name('services.edit');
    Route::put('services/{service}', [ServiceController::class, 'update'])->middleware('permission:services.update')->name('services.update');
    Route::patch('services/{service}/disable', [ServiceController::class, 'disable'])->middleware('permission:services.disable')->name('services.disable');
    Route::post('services/{service}/availability', [ServiceController::class, 'saveAvailability'])->middleware('permission:services.manage_branch_availability')->name('services.availability.store');
    Route::post('services/{service}/prices', [ServiceController::class, 'savePrice'])->middleware('permission:services.manage_prices')->name('services.prices.store');
    Route::post('services/{service}/materials', [ServiceController::class, 'saveMaterial'])->middleware('permission:services.manage_materials')->name('services.materials.store');
    Route::post('services/{service}/material-substitutes', [ServiceController::class, 'saveSubstitute'])->middleware('permission:services.manage_materials')->name('services.material-substitutes.store');
    Route::post('services/{service}/roll-profiles', [ServiceController::class, 'saveRollProfile'])->middleware('permission:services.manage_roll_profiles')->name('services.roll-profiles.store');
    Route::post('services/{service}/skills', [ServiceController::class, 'saveSkill'])->middleware('permission:services.manage_skills')->name('services.skills.store');
    Route::post('services/{service}/commissions', [ServiceController::class, 'saveCommission'])->middleware('permission:services.manage_commissions')->name('services.commissions.store');
    Route::post('services/{service}/estimate', [ServiceController::class, 'estimate'])->middleware('permission:services.view')->name('services.estimate');

    Route::get('service-packages', [ServicePackageController::class, 'index'])->middleware('permission:service_packages.view')->name('service-packages.index');
    Route::get('service-packages/create', [ServicePackageController::class, 'create'])->middleware('permission:service_packages.create')->name('service-packages.create');
    Route::post('service-packages', [ServicePackageController::class, 'store'])->middleware('permission:service_packages.create')->name('service-packages.store');
    Route::get('service-packages/{servicePackage}/edit', [ServicePackageController::class, 'edit'])->middleware('permission:service_packages.update')->name('service-packages.edit');
    Route::put('service-packages/{servicePackage}', [ServicePackageController::class, 'update'])->middleware('permission:service_packages.update')->name('service-packages.update');
    Route::patch('service-packages/{servicePackage}/disable', [ServicePackageController::class, 'disable'])->middleware('permission:service_packages.disable')->name('service-packages.disable');
    Route::post('service-packages/{servicePackage}/prices', [ServicePackageController::class, 'saveBranchPrice'])->middleware('permission:service_packages.manage_prices')->name('service-packages.prices.store');

    Route::get('promotions', [PromotionController::class, 'index'])->middleware('permission:promotions.view')->name('promotions.index');
    Route::get('promotions/create', [PromotionController::class, 'create'])->middleware('permission:promotions.manage')->name('promotions.create');
    Route::post('promotions', [PromotionController::class, 'store'])->middleware('permission:promotions.manage')->name('promotions.store');
    Route::get('promotions/{promotion}/edit', [PromotionController::class, 'edit'])->middleware('permission:promotions.manage')->name('promotions.edit');
    Route::put('promotions/{promotion}', [PromotionController::class, 'update'])->middleware('permission:promotions.manage')->name('promotions.update');

    Route::get('quotations', [QuotationController::class, 'index'])->middleware('permission:quotations.view')->name('quotations.index');
    Route::get('quotations/create', [QuotationController::class, 'create'])->middleware('permission:quotations.create')->name('quotations.create');
    Route::post('quotations', [QuotationController::class, 'store'])->middleware('permission:quotations.create')->name('quotations.store');
    Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->middleware('permission:quotations.view')->name('quotations.show');
    Route::get('quotations/{quotation}/edit', [QuotationController::class, 'edit'])->middleware('permission:quotations.update')->name('quotations.edit');
    Route::put('quotations/{quotation}', [QuotationController::class, 'update'])->middleware('permission:quotations.update')->name('quotations.update');
    Route::get('quotations/{quotation}/print', [QuotationController::class, 'print'])->middleware('permission:quotations.print')->name('quotations.print');
    Route::post('quotations/{quotation}/submit', [QuotationActionController::class, 'submit'])->middleware('permission:quotations.submit')->name('quotations.submit');
    Route::post('quotations/{quotation}/approve', [QuotationActionController::class, 'approve'])->middleware('permission:quotations.approve')->name('quotations.approve');
    Route::post('quotations/{quotation}/approval-reject', [QuotationActionController::class, 'approvalReject'])->middleware('permission:quotations.approve')->name('quotations.approval-reject');
    Route::post('quotations/{quotation}/send', [QuotationActionController::class, 'send'])->middleware('permission:quotations.send')->name('quotations.send');
    Route::post('quotations/{quotation}/accept', [QuotationActionController::class, 'accept'])->middleware('permission:quotations.accept')->name('quotations.accept');
    Route::post('quotations/{quotation}/reject', [QuotationActionController::class, 'reject'])->middleware('permission:quotations.reject')->name('quotations.reject');
    Route::post('quotations/{quotation}/cancel', [QuotationActionController::class, 'cancel'])->middleware('permission:quotations.cancel')->name('quotations.cancel');
    Route::post('quotations/{quotation}/version', [QuotationActionController::class, 'version'])->middleware('permission:quotations.create_version')->name('quotations.version');
    Route::post('quotations/{quotation}/appointment', [QuotationActionController::class, 'appointment'])->middleware('permission:appointments.create')->name('quotations.appointment');

    Route::get('appointments/calendar', [AppointmentController::class, 'calendar'])->middleware('permission:appointments.calendar')->name('appointments.calendar');
    Route::get('appointments', [AppointmentController::class, 'index'])->middleware('permission:appointments.view')->name('appointments.index');
    Route::get('appointments/create', [AppointmentController::class, 'create'])->middleware('permission:appointments.create')->name('appointments.create');
    Route::post('appointments', [AppointmentController::class, 'store'])->middleware('permission:appointments.create')->name('appointments.store');
    Route::get('appointments/{appointment}', [AppointmentController::class, 'show'])->middleware('permission:appointments.view')->name('appointments.show');
    Route::get('appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->middleware('permission:appointments.update')->name('appointments.edit');
    Route::put('appointments/{appointment}', [AppointmentController::class, 'update'])->middleware('permission:appointments.update')->name('appointments.update');
    Route::post('appointments/{appointment}/confirm', [AppointmentActionController::class, 'confirm'])->middleware('permission:appointments.confirm')->name('appointments.confirm');
    Route::post('appointments/{appointment}/check-in', [AppointmentActionController::class, 'checkIn'])->middleware('permission:appointments.check_in')->name('appointments.check-in');
    Route::post('appointments/{appointment}/cancel', [AppointmentActionController::class, 'cancel'])->middleware('permission:appointments.cancel')->name('appointments.cancel');
    Route::post('appointments/{appointment}/no-show', [AppointmentActionController::class, 'noShow'])->middleware('permission:appointments.mark_no_show')->name('appointments.no-show');
    Route::post('appointments/{appointment}/deposits', [AppointmentActionController::class, 'deposit'])->middleware('permission:appointment_deposits.record')->name('appointments.deposits.store');
    Route::post('appointment-deposits/{appointmentDeposit}/cancel', [AppointmentActionController::class, 'cancelDeposit'])->middleware('permission:appointment_deposits.cancel')->name('appointment-deposits.cancel');

    Route::get('work-orders', [WorkOrderController::class, 'index'])->middleware('permission:work_orders.view')->name('work-orders.index');
    Route::get('work-orders/create', [WorkOrderController::class, 'create'])->middleware('permission:work_orders.create')->name('work-orders.create');
    Route::post('work-orders', [WorkOrderController::class, 'store'])->middleware('permission:work_orders.create')->name('work-orders.store');
    Route::get('work-orders/{workOrder}', [WorkOrderController::class, 'show'])->middleware('permission:work_orders.view')->name('work-orders.show');
    Route::post('work-orders/{workOrder}/cancel', [WorkOrderActionController::class, 'cancel'])->middleware('permission:work_orders.cancel')->name('work-orders.cancel');
    Route::post('work-orders/{workOrder}/reserve-materials', [WorkOrderMaterialController::class, 'reserve'])->middleware('permission:work_order_materials.reserve')->name('work-orders.materials.reserve');
    Route::post('work-orders/{workOrder}/waste', [WorkOrderMaterialController::class, 'waste'])->middleware('permission:work_order_materials.record_waste')->name('work-orders.waste.store');
    Route::post('work-order-services/{workOrderService}/technicians', [WorkOrderActionController::class, 'assign'])->middleware('permission:work_orders.assign_technicians')->name('work-order-services.technicians.store');
    Route::post('work-order-services/{workOrderService}/{action}', [WorkOrderActionController::class, 'action'])->whereIn('action', ['start', 'pause', 'resume', 'complete', 'reopen'])->name('work-order-services.action');
    Route::get('vehicle-inspections/{vehicleInspection}', [VehicleInspectionController::class, 'show'])->middleware('permission:vehicle_inspections.view')->name('vehicle-inspections.show');
    Route::put('vehicle-inspections/{vehicleInspection}', [VehicleInspectionController::class, 'update'])->middleware('permission:vehicle_inspections.update')->name('vehicle-inspections.update');
    Route::post('vehicle-inspections/{vehicleInspection}/complete', [VehicleInspectionController::class, 'complete'])->middleware('permission:vehicle_inspections.complete')->name('vehicle-inspections.complete');
    Route::post('vehicle-inspections/{vehicleInspection}/photos', [VehicleInspectionController::class, 'photo'])->middleware('permission:vehicle_inspections.manage_photos')->name('vehicle-inspections.photos.store');
    Route::post('work-order-materials/{workOrderMaterial}/issue', [WorkOrderMaterialController::class, 'issue'])->middleware('permission:work_order_materials.issue')->name('work-order-materials.issue');
    Route::post('work-order-materials/{workOrderMaterial}/use', [WorkOrderMaterialController::class, 'useMaterial'])->middleware('permission:work_order_materials.issue')->name('work-order-materials.use');
    Route::post('work-order-materials/{workOrderMaterial}/consume-roll', [WorkOrderMaterialController::class, 'consumeRoll'])->middleware('permission:work_order_materials.consume_roll')->name('work-order-materials.consume-roll');
    Route::post('work-order-materials/{workOrderMaterial}/consume-scrap', [WorkOrderMaterialController::class, 'consumeScrap'])->middleware('permission:work_order_materials.consume_scrap')->name('work-order-materials.consume-scrap');
    Route::post('work-order-materials/{workOrderMaterial}/return', [WorkOrderMaterialController::class, 'returnMaterial'])->middleware('permission:work_order_materials.return')->name('work-order-materials.return');

    Route::get('quality/templates', [QualityChecklistController::class, 'index'])->middleware('permission:quality_checks.manage_templates')->name('quality-templates.index');
    Route::get('quality/templates/create', [QualityChecklistController::class, 'create'])->middleware('permission:quality_checks.manage_templates')->name('quality-templates.create');
    Route::post('quality/templates', [QualityChecklistController::class, 'store'])->middleware('permission:quality_checks.manage_templates')->name('quality-templates.store');
    Route::patch('quality/templates/{qualityTemplate}/toggle', [QualityChecklistController::class, 'toggle'])->middleware('permission:quality_checks.manage_templates')->name('quality-templates.toggle');
    Route::get('quality-checks', [QualityCheckController::class, 'index'])->middleware('permission:quality_checks.view')->name('quality-checks.index');
    Route::post('work-orders/{workOrder}/quality-checks', [QualityCheckController::class, 'start'])->middleware('permission:quality_checks.create')->name('quality-checks.start');
    Route::get('quality-checks/{qualityCheck}', [QualityCheckController::class, 'show'])->middleware('permission:quality_checks.view')->name('quality-checks.show');
    Route::put('quality-checks/{qualityCheck}/items', [QualityCheckController::class, 'items'])->middleware('permission:quality_checks.perform')->name('quality-checks.items');
    Route::post('quality-checks/{qualityCheck}/{action}', [QualityCheckController::class, 'action'])->whereIn('action', ['pass', 'fail'])->name('quality-checks.action');
    Route::post('quality-checks/{qualityCheck}/photos', [QualityCheckController::class, 'photo'])->middleware('permission:quality_checks.perform')->name('quality-checks.photos.store');

    Route::get('rework-orders', [ReworkOrderController::class, 'index'])->middleware('permission:rework_orders.view')->name('rework-orders.index');
    Route::get('rework-orders/{reworkOrder}', [ReworkOrderController::class, 'show'])->middleware('permission:rework_orders.view')->name('rework-orders.show');
    Route::post('rework-orders/{reworkOrder}/{action}', [ReworkOrderController::class, 'action'])->whereIn('action', ['approve', 'start', 'service-complete', 'complete'])->name('rework-orders.action');
    Route::post('rework-orders/{reworkOrder}/photos', [ReworkOrderController::class, 'photo'])->middleware('permission:rework_orders.complete')->name('rework-orders.photos.store');
    Route::post('rework-orders/{reworkOrder}/materials', [ReworkOrderController::class, 'material'])->middleware('permission:work_order_materials.reserve')->name('rework-orders.materials.store');
    Route::post('rework-materials/{workOrderMaterial}/reserve', [ReworkOrderController::class, 'reserveMaterial'])->middleware('permission:work_order_materials.reserve')->name('rework-orders.materials.reserve');

    Route::get('deliveries', [DeliveryController::class, 'index'])->middleware('permission:work_orders.deliver')->name('deliveries.index');
    Route::get('deliveries/{workOrder}', [DeliveryController::class, 'show'])->middleware('permission:vehicle_inspections.delivery')->name('deliveries.show');
    Route::put('deliveries/{workOrder}/inspection', [DeliveryController::class, 'update'])->middleware('permission:vehicle_inspections.delivery')->name('deliveries.inspection.update');
    Route::post('deliveries/{workOrder}/inspection/complete', [DeliveryController::class, 'complete'])->middleware('permission:vehicle_inspections.delivery')->name('deliveries.inspection.complete');
    Route::post('deliveries/{workOrder}/photos', [DeliveryController::class, 'photo'])->middleware('permission:vehicle_inspections.delivery_photos')->name('deliveries.photos.store');
    Route::post('deliveries/{workOrder}/deliver', [DeliveryController::class, 'deliver'])->middleware('permission:work_orders.deliver')->name('deliveries.deliver');

    Route::get('warranties', [WarrantyController::class, 'index'])->middleware('permission:warranties.view')->name('warranties.index');
    Route::post('warranties/issue', [WarrantyController::class, 'issue'])->middleware('permission:warranties.issue')->name('warranties.issue');
    Route::get('warranties/{warranty}', [WarrantyController::class, 'show'])->middleware('permission:warranties.view')->name('warranties.show');
    Route::get('warranties/{warranty}/print', [WarrantyController::class, 'print'])->middleware('permission:warranties.print')->name('warranties.print');
    Route::post('warranties/{warranty}/void', [WarrantyController::class, 'void'])->middleware('permission:warranties.void')->name('warranties.void');

    Route::get('warranty-claims', [WarrantyClaimController::class, 'index'])->middleware('permission:warranty_claims.view')->name('warranty-claims.index');
    Route::get('warranty-claims/create', [WarrantyClaimController::class, 'create'])->middleware('permission:warranty_claims.create')->name('warranty-claims.create');
    Route::post('warranty-claims', [WarrantyClaimController::class, 'store'])->middleware('permission:warranty_claims.create')->name('warranty-claims.store');
    Route::get('warranty-claims/{warrantyClaim}', [WarrantyClaimController::class, 'show'])->middleware('permission:warranty_claims.view')->name('warranty-claims.show');
    Route::post('warranty-claims/{warrantyClaim}/inspect', [WarrantyClaimController::class, 'inspect'])->middleware('permission:warranty_claims.inspect')->name('warranty-claims.inspect');
    Route::post('warranty-claims/{warrantyClaim}/decision', [WarrantyClaimController::class, 'decide'])->middleware('permission:warranty_claims.decide')->name('warranty-claims.decide');
    Route::post('warranty-claims/{warrantyClaim}/rework', [WarrantyClaimController::class, 'rework'])->middleware('permission:warranty_claims.approve')->name('warranty-claims.rework');
    Route::post('warranty-claims/{warrantyClaim}/resolve', [WarrantyClaimController::class, 'resolve'])->middleware('permission:warranty_claims.resolve')->name('warranty-claims.resolve');
    Route::post('warranty-claims/{warrantyClaim}/photos', [WarrantyClaimController::class, 'photo'])->middleware('permission:warranty_claims.inspect')->name('warranty-claims.photos.store');

    Route::get('sales-invoices', [SalesInvoiceController::class, 'index'])->middleware('permission:sales_invoices.view')->name('sales-invoices.index');
    Route::get('sales-invoices/create', [SalesInvoiceController::class, 'create'])->middleware('permission:sales_invoices.direct_sale')->name('sales-invoices.create');
    Route::post('sales-invoices', [SalesInvoiceController::class, 'store'])->middleware('permission:sales_invoices.direct_sale')->name('sales-invoices.store');
    Route::post('work-orders/{workOrder}/invoice', [SalesInvoiceController::class, 'fromWorkOrder'])->middleware('permission:sales_invoices.create')->name('work-orders.invoice');
    Route::get('sales-invoices/{salesInvoice}', [SalesInvoiceController::class, 'show'])->middleware('permission:sales_invoices.view')->name('sales-invoices.show');
    Route::get('sales-invoices/{salesInvoice}/print', [SalesInvoiceController::class, 'print'])->middleware('permission:sales_invoices.print')->name('sales-invoices.print');
    Route::post('sales-invoices/{salesInvoice}/{action}', [SalesInvoiceController::class, 'action'])->whereIn('action', ['submit', 'approve', 'issue', 'cancel', 'void'])->name('sales-invoices.action');
    Route::post('sales-invoice-items/{salesInvoiceItem}/return', [SalesInvoiceController::class, 'returnProduct'])->middleware('permission:sales_credit_notes.create')->name('sales-invoice-items.return');

    Route::get('customer-payments', [CustomerPaymentController::class, 'index'])->middleware('permission:customer_payments.view')->name('customer-payments.index');
    Route::get('customer-payments/create', [CustomerPaymentController::class, 'create'])->middleware('permission:customer_payments.record')->name('customer-payments.create');
    Route::post('customer-payments', [CustomerPaymentController::class, 'store'])->middleware('permission:customer_payments.record')->name('customer-payments.store');
    Route::get('customer-payments/{customerPayment}', [CustomerPaymentController::class, 'show'])->middleware('permission:customer_payments.view')->name('customer-payments.show');
    Route::post('customer-payments/{customerPayment}/approve', [CustomerPaymentController::class, 'approve'])->middleware('permission:customer_payments.approve')->name('customer-payments.approve');
    Route::post('customer-payments/{customerPayment}/allocations', [CustomerPaymentController::class, 'allocate'])->middleware('permission:customer_payments.allocate')->name('customer-payments.allocate');
    Route::post('payment-allocations/{paymentAllocation}/reverse', [CustomerPaymentController::class, 'reverse'])->middleware('permission:customer_payments.reverse_allocation')->name('payment-allocations.reverse');
    Route::get('customer-payments/{customerPayment}/receipt', [CustomerPaymentController::class, 'receipt'])->middleware('permission:customer_payments.print')->name('customer-payments.receipt');
    Route::post('appointment-deposits/{appointmentDeposit}/convert', [CustomerPaymentController::class, 'convert'])->middleware('permission:customer_payments.record')->name('appointment-deposits.convert');

    Route::get('sales-credit-notes', [SalesCreditNoteController::class, 'index'])->middleware('permission:sales_credit_notes.view')->name('sales-credit-notes.index');
    Route::get('sales-invoices/{salesInvoice}/credit-note', [SalesCreditNoteController::class, 'create'])->middleware('permission:sales_credit_notes.create')->name('sales-credit-notes.create');
    Route::post('sales-credit-notes', [SalesCreditNoteController::class, 'store'])->middleware('permission:sales_credit_notes.create')->name('sales-credit-notes.store');
    Route::get('sales-credit-notes/{salesCreditNote}', [SalesCreditNoteController::class, 'show'])->middleware('permission:sales_credit_notes.view')->name('sales-credit-notes.show');
    Route::get('sales-credit-notes/{salesCreditNote}/print', [SalesCreditNoteController::class, 'print'])->middleware('permission:sales_credit_notes.print')->name('sales-credit-notes.print');
    Route::post('sales-credit-notes/{salesCreditNote}/{action}', [SalesCreditNoteController::class, 'action'])->whereIn('action', ['approve', 'issue'])->name('sales-credit-notes.action');

    Route::get('customer-refunds', [CustomerRefundController::class, 'index'])->middleware('permission:customer_refunds.view')->name('customer-refunds.index');
    Route::get('customer-refunds/create', [CustomerRefundController::class, 'create'])->middleware('permission:customer_refunds.create')->name('customer-refunds.create');
    Route::post('customer-refunds', [CustomerRefundController::class, 'store'])->middleware('permission:customer_refunds.create')->name('customer-refunds.store');
    Route::get('customer-refunds/{customerRefund}', [CustomerRefundController::class, 'show'])->middleware('permission:customer_refunds.view')->name('customer-refunds.show');
    Route::post('customer-refunds/{customerRefund}/{action}', [CustomerRefundController::class, 'action'])->whereIn('action', ['approve', 'process'])->name('customer-refunds.action');

    Route::get('customers/{customer}/statement', [SalesReportController::class, 'statement'])->middleware('permission:customer_statements.view')->name('customer-statements.show');
    Route::get('reports/accounts-receivable-aging', [SalesReportController::class, 'aging'])->middleware('permission:accounts_receivable.aging')->name('sales-reports.aging');

    Route::get('suppliers', [SupplierController::class, 'index'])->middleware('permission:suppliers.view')->name('suppliers.index');
    Route::get('suppliers/create', [SupplierController::class, 'create'])->middleware('permission:suppliers.create')->name('suppliers.create');
    Route::post('suppliers', [SupplierController::class, 'store'])->middleware('permission:suppliers.create')->name('suppliers.store');
    Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->middleware('permission:suppliers.view')->name('suppliers.show');
    Route::get('suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->middleware('permission:suppliers.update')->name('suppliers.edit');
    Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:suppliers.update')->name('suppliers.update');
    Route::patch('suppliers/{supplier}/status/{status}', [SupplierController::class, 'status'])->middleware('permission:suppliers.disable')->whereIn('status', ['active', 'inactive', 'suspended', 'blocked'])->name('suppliers.status');
    Route::post('suppliers/{supplier}/contacts', [SupplierController::class, 'contact'])->middleware('permission:suppliers.update')->name('suppliers.contacts.store');
    Route::post('suppliers/{supplier}/addresses', [SupplierController::class, 'address'])->middleware('permission:suppliers.update')->name('suppliers.addresses.store');
    Route::post('suppliers/{supplier}/products', [SupplierController::class, 'product'])->middleware('permission:suppliers.update')->name('suppliers.products.store');

    Route::get('purchase-requisitions', [PurchaseRequisitionController::class, 'index'])->middleware('permission:purchase_requisitions.view')->name('purchase-requisitions.index');
    Route::get('purchase-requisitions/create', [PurchaseRequisitionController::class, 'create'])->middleware('permission:purchase_requisitions.create')->name('purchase-requisitions.create');
    Route::post('purchase-requisitions', [PurchaseRequisitionController::class, 'store'])->middleware('permission:purchase_requisitions.create')->name('purchase-requisitions.store');
    Route::get('purchase-requisitions/{purchaseRequisition}', [PurchaseRequisitionController::class, 'show'])->middleware('permission:purchase_requisitions.view')->name('purchase-requisitions.show');
    Route::post('purchase-requisitions/{purchaseRequisition}/{action}', [PurchaseRequisitionController::class, 'action'])->whereIn('action', ['submit', 'approve', 'reject', 'cancel'])->name('purchase-requisitions.action');

    Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('permission:purchase_orders.view')->name('purchase-orders.index');
    Route::get('purchase-orders/create', [PurchaseOrderController::class, 'create'])->middleware('permission:purchase_orders.create')->name('purchase-orders.create');
    Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('permission:purchase_orders.create')->name('purchase-orders.store');
    Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->middleware('permission:purchase_orders.view')->name('purchase-orders.show');
    Route::post('purchase-orders/{purchaseOrder}/{action}', [PurchaseOrderController::class, 'action'])->whereIn('action', ['submit', 'approve', 'send', 'cancel'])->name('purchase-orders.action');

    Route::get('goods-receipts', [GoodsReceiptController::class, 'index'])->middleware('permission:goods_receipts.view')->name('goods-receipts.index');
    Route::get('goods-receipts/create', [GoodsReceiptController::class, 'create'])->middleware('permission:goods_receipts.create')->name('goods-receipts.create');
    Route::post('goods-receipts', [GoodsReceiptController::class, 'store'])->middleware('permission:goods_receipts.create')->name('goods-receipts.store');
    Route::get('goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->middleware('permission:goods_receipts.view')->name('goods-receipts.show');
    Route::post('goods-receipts/{goodsReceipt}/receive', [GoodsReceiptController::class, 'receive'])->middleware('permission:goods_receipts.update')->name('goods-receipts.receive');
    Route::post('goods-receipts/{goodsReceipt}/inspect', [GoodsReceiptController::class, 'inspect'])->middleware('permission:goods_receipts.inspect')->name('goods-receipts.inspect');
    Route::post('goods-receipts/{goodsReceipt}/attachments', [AttachmentController::class, 'storeForGoodsReceipt'])->middleware('permission:goods_receipts.inspect')->name('goods-receipts.attachments.store');
    Route::post('goods-receipts/{goodsReceipt}/post', [GoodsReceiptController::class, 'post'])->middleware('permission:goods_receipts.post')->name('goods-receipts.post');

    Route::get('purchase-returns', [PurchaseReturnController::class, 'index'])->middleware('permission:purchase_returns.view')->name('purchase-returns.index');
    Route::get('purchase-returns/create', [PurchaseReturnController::class, 'create'])->middleware('permission:purchase_returns.create')->name('purchase-returns.create');
    Route::post('purchase-returns', [PurchaseReturnController::class, 'store'])->middleware('permission:purchase_returns.create')->name('purchase-returns.store');
    Route::get('purchase-returns/{purchaseReturn}', [PurchaseReturnController::class, 'show'])->middleware('permission:purchase_returns.view')->name('purchase-returns.show');
    Route::post('purchase-returns/{purchaseReturn}/{action}', [PurchaseReturnController::class, 'action'])->whereIn('action', ['submit', 'approve', 'post'])->name('purchase-returns.action');

    Route::get('supplier-invoices', [SupplierInvoiceController::class, 'index'])->middleware('permission:supplier_invoices.view')->name('supplier-invoices.index');
    Route::get('supplier-invoices/create', [SupplierInvoiceController::class, 'create'])->middleware('permission:supplier_invoices.create')->name('supplier-invoices.create');
    Route::post('supplier-invoices', [SupplierInvoiceController::class, 'store'])->middleware('permission:supplier_invoices.create')->name('supplier-invoices.store');
    Route::get('supplier-invoices/{supplierInvoice}', [SupplierInvoiceController::class, 'show'])->middleware('permission:supplier_invoices.view')->name('supplier-invoices.show');
    Route::post('supplier-invoices/{supplierInvoice}/variances', [SupplierInvoiceController::class, 'approveVariance'])->middleware('permission:supplier_invoices.override_variance')->name('supplier-invoices.variances.approve');
    Route::post('supplier-invoices/{supplierInvoice}/{action}', [SupplierInvoiceController::class, 'action'])->whereIn('action', ['submit', 'approve', 'post'])->name('supplier-invoices.action');

    Route::get('supplier-payments', [SupplierPaymentController::class, 'index'])->middleware('permission:supplier_payments.view')->name('supplier-payments.index');
    Route::get('supplier-payments/create', [SupplierPaymentController::class, 'create'])->middleware('permission:supplier_payments.create')->name('supplier-payments.create');
    Route::post('supplier-payments', [SupplierPaymentController::class, 'store'])->middleware('permission:supplier_payments.create')->name('supplier-payments.store');
    Route::get('supplier-payments/{supplierPayment}', [SupplierPaymentController::class, 'show'])->middleware('permission:supplier_payments.view')->name('supplier-payments.show');
    Route::post('supplier-payments/{supplierPayment}/allocations', [SupplierPaymentController::class, 'allocate'])->middleware('permission:supplier_payments.allocate')->name('supplier-payments.allocate');
    Route::post('supplier-payments/{supplierPayment}/{action}', [SupplierPaymentController::class, 'action'])->whereIn('action', ['approve', 'process'])->name('supplier-payments.action');
    Route::post('supplier-payment-allocations/{supplierPaymentAllocation}/reverse', [SupplierPaymentController::class, 'reverse'])->middleware('permission:supplier_payments.reverse_allocation')->name('supplier-payment-allocations.reverse');

    Route::get('supplier-credit-notes', [SupplierCreditNoteController::class, 'index'])->middleware('permission:supplier_credit_notes.view')->name('supplier-credit-notes.index');
    Route::get('supplier-credit-notes/create', [SupplierCreditNoteController::class, 'create'])->middleware('permission:supplier_credit_notes.create')->name('supplier-credit-notes.create');
    Route::post('supplier-credit-notes', [SupplierCreditNoteController::class, 'store'])->middleware('permission:supplier_credit_notes.create')->name('supplier-credit-notes.store');
    Route::get('supplier-credit-notes/{supplierCreditNote}', [SupplierCreditNoteController::class, 'show'])->middleware('permission:supplier_credit_notes.view')->name('supplier-credit-notes.show');
    Route::post('supplier-credit-notes/{supplierCreditNote}/{action}', [SupplierCreditNoteController::class, 'action'])->whereIn('action', ['approve', 'post'])->name('supplier-credit-notes.action');

    Route::get('suppliers/{supplier}/statement', [PurchasingReportController::class, 'statement'])->middleware('permission:supplier_statements.view')->name('supplier-statements.show');
    Route::get('reports/accounts-payable-aging', [PurchasingReportController::class, 'aging'])->middleware('permission:accounts_payable.aging')->name('purchasing-reports.aging');
    Route::get('reports/purchasing/{report}', [PurchasingReportController::class, 'operational'])->whereIn('report', ['open-orders', 'pending-receipts', 'unmatched-invoices', 'purchase-returns'])->name('purchasing-reports.operational');
});
