<?php

use App\Http\Controllers\AppointmentActionController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BranchContextController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchSettingsController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryActionController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryDocumentController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductReferenceController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\QuotationActionController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReferenceDataController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServicePackageController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\WarehouseController;
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

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware(['auth', 'active.user', 'tenant'])->group(function () {
    Route::get('dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
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
});
