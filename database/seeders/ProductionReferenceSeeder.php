<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FoundationPermissionSeeder::class,
            ReferenceDataSeeder::class,
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
            CentralWorkflowSeeder::class,
            AnalyticsReportingSeeder::class,
            CashierPermissionReconciler::class,
        ]);
    }
}
