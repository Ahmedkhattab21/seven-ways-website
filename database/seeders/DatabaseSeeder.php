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
        ]);
    }
}
