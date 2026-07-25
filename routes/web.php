<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BranchContextController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchSettingsController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ReferenceDataController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\VehicleController;
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
});
