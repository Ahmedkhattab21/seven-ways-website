<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\UatEnvironmentGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class UatPerformanceSeeder extends Seeder
{
    private const COUNTS = [
        'customers' => 500,
        'suppliers' => 100,
        'products' => 300,
        'sales_invoices' => 2000,
        'supplier_invoices' => 500,
        'stock_movements' => 5000,
        'journal_lines' => 10000,
        'approval_tasks' => 1000,
        'audit_events' => 10000,
        'notifications' => 2000,
    ];

    public function run(): void
    {
        app(UatEnvironmentGuard::class)->assertSafe();
        if (! app()->environment(['uat', 'uat.local', 'testing'])) {
            throw new RuntimeException('UatPerformanceSeeder is restricted to UAT and testing.');
        }

        $company = Company::query()->where('name', 'Seven Ways UAT Egypt')->firstOrFail();
        $branch = Branch::query()->where('company_id', $company->id)->where('code', 'UAT-CAI')->firstOrFail();
        $owner = User::query()->where('company_id', $company->id)
            ->where('email', 'uat.owner@sevenways.test')->firstOrFail();
        $currency = Currency::query()->where('code', 'EGP')->where('is_active', true)->firstOrFail();
        $unit = Unit::query()->whereNull('company_id')->where('code', 'piece')->firstOrFail();
        $tax = Tax::query()->where('company_id', $company->id)->where('code', 'VAT14-EG')->firstOrFail();
        $category = ProductCategory::query()->where('company_id', $company->id)
            ->where('code', 'UAT-PRODUCTS')->firstOrFail();
        $warehouse = Warehouse::query()->where('company_id', $company->id)
            ->where('code', 'UAT-CAI-MAIN')->firstOrFail();
        $period = AccountingPeriod::query()->where('company_id', $company->id)
            ->where('status', 'open')->firstOrFail();

        DB::transaction(function () use (
            $company,
            $branch,
            $owner,
            $currency,
            $unit,
            $tax,
            $category,
            $warehouse,
            $period
        ): void {
            $this->customers($company->id, $branch->id, $owner->id);
            $this->suppliers($company->id, $currency->id, $owner->id);
            $this->products($company->id, $category->id, $unit->id, $tax->id, $owner->id);
            $customerIds = Customer::query()->where('company_id', $company->id)
                ->where('customer_code', 'like', 'UAT-PERF-CUS-%')->pluck('id')->all();
            $supplierIds = Supplier::query()->where('company_id', $company->id)
                ->where('supplier_code', 'like', 'UAT-PERF-SUP-%')->pluck('id')->all();
            $this->salesInvoices($company->id, $branch->id, $currency->id, $owner->id, $customerIds);
            $this->supplierInvoices($company->id, $branch->id, $currency->id, $owner->id, $supplierIds);
            $this->stockMovements(
                $company->id,
                $branch->id,
                $warehouse->id,
                $unit->id,
                $owner->id
            );
            $this->journalLines(
                $company->id,
                $branch->id,
                $currency->id,
                $owner->id,
                $period
            );
            $this->approvalTasks($company->id, $branch->id, $currency->id, $owner->id);
            $this->auditEvents($company->id, $branch->id, $owner->id);
            $this->notifications($company->id, $branch->id, $owner->id);
        });
    }

    private function customers(int $companyId, int $branchId, int $ownerId): void
    {
        $this->chunks(self::COUNTS['customers'], function (int $index) use ($companyId, $branchId, $ownerId): array {
            $code = 'UAT-PERF-CUS-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT);

            return [
                'uuid' => $this->uuid($code),
                'company_id' => $companyId,
                'created_branch_id' => $branchId,
                'assigned_branch_id' => $branchId,
                'customer_code' => $code,
                'customer_type' => $index % 5 === 0 ? 'company' : 'individual',
                'name' => "عميل أداء تجريبي {$index}",
                'phone' => '+2011'.str_pad((string) $index, 8, '0', STR_PAD_LEFT),
                'normalized_phone' => '2011'.str_pad((string) $index, 8, '0', STR_PAD_LEFT),
                'preferred_language' => 'ar',
                'credit_limit' => $index % 3 === 0 ? 50000 : 0,
                'payment_term_days' => $index % 3 === 0 ? 30 : 0,
                'status' => 'active',
                'created_by' => $ownerId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, 'customers', ['company_id', 'customer_code'], ['name', 'updated_at']);
    }

    private function suppliers(int $companyId, int $currencyId, int $ownerId): void
    {
        $this->chunks(self::COUNTS['suppliers'], function (int $index) use ($companyId, $currencyId, $ownerId): array {
            $code = 'UAT-PERF-SUP-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT);

            return [
                'uuid' => $this->uuid($code),
                'company_id' => $companyId,
                'supplier_code' => $code,
                'name' => "مورد أداء تجريبي {$index}",
                'supplier_type' => 'other',
                'currency_id' => $currencyId,
                'payment_terms_days' => 30,
                'credit_limit' => 100000,
                'status' => 'active',
                'created_by' => $ownerId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, 'suppliers', ['company_id', 'supplier_code'], ['name', 'updated_at']);
    }

    private function products(
        int $companyId,
        int $categoryId,
        int $unitId,
        int $taxId,
        int $ownerId
    ): void {
        $this->chunks(self::COUNTS['products'], function (int $index) use (
            $companyId,
            $categoryId,
            $unitId,
            $taxId,
            $ownerId
        ): array {
            $sku = 'UAT-PERF-PRD-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT);

            return [
                'uuid' => $this->uuid($sku),
                'company_id' => $companyId,
                'category_id' => $categoryId,
                'sku' => $sku,
                'name' => "منتج أداء تجريبي {$index}",
                'product_type' => 'stock',
                'tracking_type' => 'quantity',
                'purchase_unit_id' => $unitId,
                'stock_unit_id' => $unitId,
                'sale_unit_id' => $unitId,
                'default_tax_id' => $taxId,
                'costing_method' => 'weighted_average',
                'standard_cost' => 50,
                'default_sale_price' => 100,
                'minimum_stock' => 5,
                'is_sellable' => true,
                'is_purchasable' => true,
                'is_consumable' => false,
                'is_active' => true,
                'created_by' => $ownerId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, 'products', ['company_id', 'sku'], ['name', 'updated_at']);
    }

    private function salesInvoices(
        int $companyId,
        int $branchId,
        int $currencyId,
        int $ownerId,
        array $customerIds
    ): void {
        if ($customerIds === []) {
            throw new RuntimeException('Performance customers were not created.');
        }
        $this->chunks(self::COUNTS['sales_invoices'], function (int $index) use (
            $companyId,
            $branchId,
            $currencyId,
            $ownerId,
            $customerIds
        ): array {
            $number = 'UAT-PERF-SINV-'.str_pad((string) $index, 5, '0', STR_PAD_LEFT);

            return [
                'uuid' => $this->uuid($number),
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'invoice_number' => $number,
                'invoice_type' => 'direct_sale',
                'customer_id' => $customerIds[($index - 1) % count($customerIds)],
                'currency_id' => $currencyId,
                'status' => 'draft',
                'invoice_date' => '2026-07-15',
                'price_includes_tax' => false,
                'subtotal' => 100,
                'discount_value' => 0,
                'discount_amount' => 0,
                'tax_amount' => 14,
                'rounding_amount' => 0,
                'total' => 114,
                'paid_amount' => 0,
                'credited_amount' => 0,
                'refunded_amount' => 0,
                'balance_due' => 114,
                'customer_name_snapshot' => "عميل أداء تجريبي {$index}",
                'created_by' => $ownerId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, 'sales_invoices', ['company_id', 'invoice_number'], ['updated_at']);
    }

    private function supplierInvoices(
        int $companyId,
        int $branchId,
        int $currencyId,
        int $ownerId,
        array $supplierIds
    ): void {
        if ($supplierIds === []) {
            throw new RuntimeException('Performance suppliers were not created.');
        }
        $this->chunks(self::COUNTS['supplier_invoices'], function (int $index) use (
            $companyId,
            $branchId,
            $currencyId,
            $ownerId,
            $supplierIds
        ): array {
            $number = 'UAT-PERF-PINV-'.str_pad((string) $index, 5, '0', STR_PAD_LEFT);

            return [
                'uuid' => $this->uuid($number),
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'supplier_id' => $supplierIds[($index - 1) % count($supplierIds)],
                'supplier_invoice_number' => 'EXT-'.$number,
                'internal_invoice_number' => $number,
                'currency_id' => $currencyId,
                'status' => 'draft',
                'invoice_date' => '2026-07-15',
                'subtotal' => 100,
                'discount_amount' => 0,
                'tax_amount' => 14,
                'shipping_amount' => 0,
                'other_charges' => 0,
                'rounding_amount' => 0,
                'total' => 114,
                'paid_amount' => 0,
                'credited_amount' => 0,
                'balance_due' => 114,
                'supplier_name_snapshot' => "مورد أداء تجريبي {$index}",
                'created_by' => $ownerId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, 'supplier_invoices', ['company_id', 'branch_id', 'internal_invoice_number'], ['updated_at']);
    }

    private function stockMovements(
        int $companyId,
        int $branchId,
        int $warehouseId,
        int $unitId,
        int $ownerId
    ): void {
        $productIds = DB::table('products')->where('company_id', $companyId)
            ->where('sku', 'like', 'UAT-PERF-PRD-%')->pluck('id')->all();
        $this->chunks(self::COUNTS['stock_movements'], function (int $index) use (
            $companyId,
            $branchId,
            $warehouseId,
            $unitId,
            $ownerId,
            $productIds
        ): array {
            $number = 'UAT-PERF-MOV-'.str_pad((string) $index, 6, '0', STR_PAD_LEFT);

            return [
                'uuid' => $this->uuid($number),
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productIds[($index - 1) % count($productIds)],
                'movement_number' => $number,
                'movement_type' => 'uat_performance',
                'direction' => $index % 2 === 0 ? 'out' : 'in',
                'reference_type' => 'uat_performance',
                'reference_id' => $index,
                'quantity' => 1,
                'unit_id' => $unitId,
                'stock_quantity' => 1,
                'unit_cost' => 50,
                'total_cost' => 50,
                'balance_before' => 0,
                'balance_after' => $index % 2 === 0 ? -1 : 1,
                'occurred_at' => '2026-07-15 12:00:00',
                'notes' => 'Synthetic UAT performance row; not a posted business document.',
                'created_by' => $ownerId,
                'created_at' => now(),
            ];
        }, 'stock_movements', ['movement_number'], ['occurred_at']);
    }

    private function journalLines(
        int $companyId,
        int $branchId,
        int $currencyId,
        int $ownerId,
        AccountingPeriod $period
    ): void {
        $journal = JournalEntry::query()->firstOrNew([
            'company_id' => $companyId,
            'journal_number' => 'UAT-PERF-JE-00001',
        ]);
        $journal->forceFill([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'fiscal_year_id' => $period->fiscal_year_id,
            'accounting_period_id' => $period->id,
            'journal_number' => 'UAT-PERF-JE-00001',
            'entry_type' => 'manual',
            'status' => 'draft',
            'entry_date' => '2026-07-15',
            'currency_id' => $currencyId,
            'exchange_rate' => 1,
            'description' => 'Synthetic balanced UAT performance journal',
            'total_debit' => self::COUNTS['journal_lines'] / 2,
            'total_credit' => self::COUNTS['journal_lines'] / 2,
            'base_total_debit' => self::COUNTS['journal_lines'] / 2,
            'base_total_credit' => self::COUNTS['journal_lines'] / 2,
            'is_automatic' => false,
            'is_reversal' => false,
            'is_opening' => false,
            'is_adjusting' => false,
            'created_by' => $ownerId,
        ])->save();
        $debit = Account::query()->where('company_id', $companyId)
            ->where('account_code', 'UAT-CASH-CAI')->firstOrFail();
        $credit = Account::query()->where('company_id', $companyId)
            ->where('account_code', 'UAT-BANK-CAI')->firstOrFail();

        $this->chunks(self::COUNTS['journal_lines'], function (int $index) use (
            $journal,
            $debit,
            $credit,
            $branchId,
            $currencyId
        ): array {
            $isDebit = $index % 2 === 1;

            return [
                'uuid' => $this->uuid('UAT-PERF-JEL-'.$index),
                'journal_entry_id' => $journal->id,
                'line_number' => $index,
                'account_id' => $isDebit ? $debit->id : $credit->id,
                'branch_id' => $branchId,
                'currency_id' => $currencyId,
                'exchange_rate' => 1,
                'debit_amount' => $isDebit ? 1 : 0,
                'credit_amount' => $isDebit ? 0 : 1,
                'base_debit_amount' => $isDebit ? 1 : 0,
                'base_credit_amount' => $isDebit ? 0 : 1,
                'tax_component' => 'none',
                'description' => 'UAT performance line',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, 'journal_entry_lines', ['journal_entry_id', 'line_number'], ['updated_at']);
    }

    private function approvalTasks(int $companyId, int $branchId, int $currencyId, int $ownerId): void
    {
        $this->chunks(self::COUNTS['approval_tasks'], function (int $index) use (
            $companyId,
            $branchId,
            $currencyId,
            $ownerId
        ): array {
            $key = 'uat-performance-approval-'.$index;

            return [
                'uuid' => $this->uuid($key),
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'approvable_type' => null,
                'approvable_id' => null,
                'module' => 'uat_performance',
                'document_type' => 'SyntheticPerformanceFixture',
                'document_number' => 'UAT-PERF-APR-'.$index,
                'stage' => 'approval',
                'status' => 'pending',
                'requested_by' => $ownerId,
                'requested_at' => '2026-07-15 12:00:00',
                'required_permission' => 'dashboard.view',
                'amount_snapshot' => 100,
                'currency_id' => $currencyId,
                'priority' => 'normal',
                'idempotency_key' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, 'approval_tasks', ['idempotency_key'], ['updated_at']);
    }

    private function auditEvents(int $companyId, int $branchId, int $ownerId): void
    {
        $this->chunks(self::COUNTS['audit_events'], function (int $index) use (
            $companyId,
            $branchId,
            $ownerId
        ): array {
            $key = 'uat-performance-audit-'.$index;

            return [
                'uuid' => $this->uuid($key),
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'user_id' => $ownerId,
                'effective_actor_id' => $ownerId,
                'event_type' => 'uat.performance.generated',
                'module' => 'uat_performance',
                'action' => 'generated',
                'auditable_type' => null,
                'auditable_id' => null,
                'document_number' => 'UAT-PERF-AUD-'.$index,
                'correlation_id' => hash('sha256', $key),
                'occurred_at' => '2026-07-15 12:00:00',
                'created_at' => now(),
            ];
        }, 'audit_events', ['uuid'], ['occurred_at']);
    }

    private function notifications(int $companyId, int $branchId, int $ownerId): void
    {
        $this->chunks(self::COUNTS['notifications'], function (int $index) use (
            $companyId,
            $branchId,
            $ownerId
        ): array {
            $key = 'uat-performance-notification-'.$index;

            return [
                'uuid' => $this->uuid($key),
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'user_id' => $ownerId,
                'type' => 'uat_performance',
                'severity' => 'info',
                'title' => "إشعار أداء UAT {$index}",
                'message' => 'Synthetic UAT performance notification.',
                'related_type' => null,
                'related_id' => null,
                'idempotency_key' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, 'system_notifications', ['idempotency_key'], ['updated_at']);
    }

    private function chunks(
        int $count,
        callable $row,
        string $table,
        array $uniqueBy,
        array $update
    ): void {
        foreach (array_chunk(range(1, $count), 500) as $indices) {
            DB::table($table)->upsert(
                array_map($row, $indices),
                $uniqueBy,
                $update
            );
        }
    }

    private function uuid(string $key): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'https://sevenways.test/'.$key)->toString();
    }
}
