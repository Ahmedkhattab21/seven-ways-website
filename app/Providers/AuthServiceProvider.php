<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\AppointmentDeposit;
use App\Models\Attachment;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\BranchSetting;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\DocumentSequence;
use App\Models\EmployeeServiceSkill;
use App\Models\FiscalYear;
use App\Models\GoodsReceipt;
use App\Models\InventoryCount;
use App\Models\InventoryReservation;
use App\Models\InventoryRoll;
use App\Models\Lead;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseReturn;
use App\Models\QualityCheck;
use App\Models\QualityChecklistTemplate;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\ReworkOrder;
use App\Models\Role;
use App\Models\RollScrap;
use App\Models\SalesCreditNote;
use App\Models\SalesInvoice;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceCommissionRule;
use App\Models\ServiceMaterialRequirement;
use App\Models\ServicePackage;
use App\Models\ServicePrice;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferDiscrepancy;
use App\Models\Supplier;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleInspection;
use App\Models\VehicleModel;
use App\Models\VehicleSize;
use App\Models\VehicleType;
use App\Models\Warehouse;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use App\Models\WorkOrder;
use App\Models\WorkOrderMaterial;
use App\Models\WorkOrderService as WorkOrderServiceModel;
use App\Models\WorkOrderWasteRecord;
use App\Policies\AppointmentDepositPolicy;
use App\Policies\AppointmentPolicy;
use App\Policies\AttachmentPolicy;
use App\Policies\BranchPolicy;
use App\Policies\BranchServicePolicy;
use App\Policies\BranchSettingPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\CustomerPaymentPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\CustomerRefundPolicy;
use App\Policies\EmployeeServiceSkillPolicy;
use App\Policies\GoodsReceiptPolicy;
use App\Policies\InventoryCountPolicy;
use App\Policies\InventoryReservationPolicy;
use App\Policies\InventoryRollPolicy;
use App\Policies\LeadPolicy;
use App\Policies\PaymentAllocationPolicy;
use App\Policies\ProductBrandPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PromotionPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseRequisitionPolicy;
use App\Policies\PurchaseReturnPolicy;
use App\Policies\QualityChecklistTemplatePolicy;
use App\Policies\QualityCheckPolicy;
use App\Policies\QuotationItemPolicy;
use App\Policies\QuotationPolicy;
use App\Policies\ReferenceDataPolicy;
use App\Policies\ReworkOrderPolicy;
use App\Policies\RolePolicy;
use App\Policies\RollScrapPolicy;
use App\Policies\SalesCreditNotePolicy;
use App\Policies\SalesInvoicePolicy;
use App\Policies\ServiceCategoryPolicy;
use App\Policies\ServiceCommissionRulePolicy;
use App\Policies\ServiceMaterialRequirementPolicy;
use App\Policies\ServicePackagePolicy;
use App\Policies\ServicePolicy;
use App\Policies\ServicePricePolicy;
use App\Policies\StockAdjustmentPolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\StockTransferDiscrepancyPolicy;
use App\Policies\StockTransferPolicy;
use App\Policies\SupplierCreditNotePolicy;
use App\Policies\SupplierInvoicePolicy;
use App\Policies\SupplierPaymentPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UserPolicy;
use App\Policies\VehicleInspectionPolicy;
use App\Policies\VehiclePolicy;
use App\Policies\WarehousePolicy;
use App\Policies\WarrantyClaimPolicy;
use App\Policies\WarrantyPolicy;
use App\Policies\WorkOrderMaterialPolicy;
use App\Policies\WorkOrderPolicy;
use App\Policies\WorkOrderServicePolicy;
use App\Policies\WorkOrderWastePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Company::class => CompanyPolicy::class,
        Branch::class => BranchPolicy::class,
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        BranchSetting::class => BranchSettingPolicy::class,
        Currency::class => ReferenceDataPolicy::class,
        Tax::class => ReferenceDataPolicy::class,
        Unit::class => ReferenceDataPolicy::class,
        PaymentMethod::class => ReferenceDataPolicy::class,
        VehicleBrand::class => ReferenceDataPolicy::class,
        VehicleModel::class => ReferenceDataPolicy::class,
        VehicleSize::class => ReferenceDataPolicy::class,
        VehicleType::class => ReferenceDataPolicy::class,
        FiscalYear::class => ReferenceDataPolicy::class,
        DocumentSequence::class => ReferenceDataPolicy::class,
        Customer::class => CustomerPolicy::class,
        Vehicle::class => VehiclePolicy::class,
        Lead::class => LeadPolicy::class,
        Attachment::class => AttachmentPolicy::class,
        Product::class => ProductPolicy::class,
        ProductCategory::class => ProductCategoryPolicy::class,
        ProductBrand::class => ProductBrandPolicy::class,
        Warehouse::class => WarehousePolicy::class,
        StockMovement::class => StockMovementPolicy::class,
        InventoryRoll::class => InventoryRollPolicy::class,
        RollScrap::class => RollScrapPolicy::class,
        StockAdjustment::class => StockAdjustmentPolicy::class,
        InventoryCount::class => InventoryCountPolicy::class,
        InventoryReservation::class => InventoryReservationPolicy::class,
        StockTransfer::class => StockTransferPolicy::class,
        StockTransferDiscrepancy::class => StockTransferDiscrepancyPolicy::class,
        ServiceCategory::class => ServiceCategoryPolicy::class,
        Service::class => ServicePolicy::class,
        BranchService::class => BranchServicePolicy::class,
        ServicePrice::class => ServicePricePolicy::class,
        ServiceMaterialRequirement::class => ServiceMaterialRequirementPolicy::class,
        ServicePackage::class => ServicePackagePolicy::class,
        Promotion::class => PromotionPolicy::class,
        Quotation::class => QuotationPolicy::class,
        QuotationItem::class => QuotationItemPolicy::class,
        Appointment::class => AppointmentPolicy::class,
        AppointmentDeposit::class => AppointmentDepositPolicy::class,
        EmployeeServiceSkill::class => EmployeeServiceSkillPolicy::class,
        ServiceCommissionRule::class => ServiceCommissionRulePolicy::class,
        WorkOrder::class => WorkOrderPolicy::class,
        WorkOrderServiceModel::class => WorkOrderServicePolicy::class,
        WorkOrderMaterial::class => WorkOrderMaterialPolicy::class,
        VehicleInspection::class => VehicleInspectionPolicy::class,
        WorkOrderWasteRecord::class => WorkOrderWastePolicy::class,
        QualityChecklistTemplate::class => QualityChecklistTemplatePolicy::class,
        QualityCheck::class => QualityCheckPolicy::class,
        ReworkOrder::class => ReworkOrderPolicy::class,
        Warranty::class => WarrantyPolicy::class,
        WarrantyClaim::class => WarrantyClaimPolicy::class,
        SalesInvoice::class => SalesInvoicePolicy::class,
        CustomerPayment::class => CustomerPaymentPolicy::class,
        PaymentAllocation::class => PaymentAllocationPolicy::class,
        SalesCreditNote::class => SalesCreditNotePolicy::class,
        CustomerRefund::class => CustomerRefundPolicy::class,
        Supplier::class => SupplierPolicy::class,
        PurchaseRequisition::class => PurchaseRequisitionPolicy::class,
        PurchaseOrder::class => PurchaseOrderPolicy::class,
        GoodsReceipt::class => GoodsReceiptPolicy::class,
        PurchaseReturn::class => PurchaseReturnPolicy::class,
        SupplierInvoice::class => SupplierInvoicePolicy::class,
        SupplierPayment::class => SupplierPaymentPolicy::class,
        SupplierCreditNote::class => SupplierCreditNotePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::before(fn (User $user) => $user->hasRole('system_admin') ? true : null);
    }
}
