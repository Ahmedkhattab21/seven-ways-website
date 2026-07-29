<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        if (app()->environment('production')) {
            $this->call(ProductionReferenceSeeder::class);

            return;
        }

        $this->call([
            FoundationPermissionSeeder::class,
            ReferenceDataSeeder::class,
            SevenWaysTenantSeeder::class,
            SevenWaysOperationalSeeder::class,
            InventorySeeder::class,
            StockTransferSeeder::class,
            ServiceCatalogSeeder::class,
            QuotationAppointmentSeeder::class,
            WorkOrderSeeder::class,
            QualityWarrantySeeder::class,
            SalesReceivablesSeeder::class,
            PurchasingSeeder::class,
            AccountingFoundationSeeder::class,
            AccountingPostingSeeder::class,
            FinancialReportingSeeder::class,
            AccountingClosingSeeder::class,
            TreasuryFoundationSeeder::class,
            BankReconciliationSeeder::class,
            TreasuryOperationsSeeder::class,
            EmployeeFinanceSeeder::class,
            EmployeeManagementSeeder::class,
            CentralWorkflowSeeder::class,
            AnalyticsReportingSeeder::class,
            ThreeRoleOperatingModelSeeder::class,
        ]);
    }
}
