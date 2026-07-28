<?php

namespace App\Analytics;

use InvalidArgumentException;

class ReportRegistry
{
    private const DEFINITIONS = [
        'financial' => [
            'name' => 'التقارير المالية', 'module' => 'accounting',
            'permission' => 'reports.financial.view', 'posted_only' => true,
            'columns' => [
                'posting_date' => 'التاريخ', 'journal_number' => 'رقم القيد',
                'source_number' => 'المصدر', 'description' => 'البيان',
                'debit' => 'مدين', 'credit' => 'دائن', 'branch' => 'الفرع',
            ],
        ],
        'sales' => [
            'name' => 'تقارير المبيعات', 'module' => 'sales',
            'permission' => 'reports.sales.view', 'posted_only' => true,
            'columns' => [
                'invoice_date' => 'التاريخ', 'invoice_number' => 'الفاتورة',
                'customer' => 'العميل', 'branch' => 'الفرع', 'net_sales' => 'صافي قبل الضريبة',
                'tax' => 'الضريبة', 'total' => 'الإجمالي', 'currency' => 'العملة',
            ],
        ],
        'receivables' => [
            'name' => 'العملاء والتحصيلات', 'module' => 'receivables',
            'permission' => 'reports.receivables.view', 'posted_only' => false,
            'columns' => [
                'due_date' => 'الاستحقاق', 'invoice_number' => 'الفاتورة',
                'customer' => 'العميل', 'branch' => 'الفرع', 'balance' => 'الرصيد',
                'bucket' => 'فئة العمر', 'currency' => 'العملة',
            ],
        ],
        'purchases' => [
            'name' => 'تقارير المشتريات', 'module' => 'purchasing',
            'permission' => 'reports.purchases.view', 'posted_only' => false,
            'columns' => [
                'invoice_date' => 'التاريخ', 'invoice_number' => 'فاتورة المورد',
                'supplier' => 'المورد', 'branch' => 'الفرع', 'subtotal' => 'قبل الضريبة',
                'tax' => 'الضريبة', 'total' => 'الإجمالي', 'currency' => 'العملة',
            ],
        ],
        'payables' => [
            'name' => 'الموردون والمدفوعات', 'module' => 'payables',
            'permission' => 'reports.payables.view', 'posted_only' => false,
            'columns' => [
                'due_date' => 'الاستحقاق', 'invoice_number' => 'الفاتورة',
                'supplier' => 'المورد', 'branch' => 'الفرع', 'balance' => 'الرصيد',
                'bucket' => 'فئة العمر', 'currency' => 'العملة',
            ],
        ],
        'inventory' => [
            'name' => 'تقارير المخزون', 'module' => 'inventory',
            'permission' => 'reports.inventory.view', 'posted_only' => false,
            'columns' => [
                'sku' => 'SKU', 'product' => 'الصنف', 'branch' => 'الفرع',
                'warehouse' => 'المخزن', 'quantity' => 'الكمية',
                'available' => 'المتاح', 'unit_cost' => 'التكلفة', 'valuation' => 'القيمة',
            ],
        ],
        'treasury' => [
            'name' => 'الخزينة والبنوك', 'module' => 'treasury',
            'permission' => 'reports.treasury.view', 'posted_only' => true,
            'columns' => [
                'account_code' => 'كود الحساب', 'account' => 'الحساب',
                'type' => 'النوع', 'branch' => 'الفرع', 'balance' => 'الرصيد الدفتري',
            ],
        ],
        'employee-finance' => [
            'name' => 'مالية الموظفين', 'module' => 'employee_finance',
            'permission' => 'reports.employee_finance.view', 'posted_only' => false,
            'columns' => [
                'employee' => 'الموظف', 'branch' => 'الفرع',
                'commission_outstanding' => 'عمولات مستحقة', 'expenses_posted' => 'مصروفات مرحلة',
                'advances_outstanding' => 'سلف غير مسواة',
            ],
        ],
        'approvals' => [
            'name' => 'تحليلات الاعتمادات', 'module' => 'approvals',
            'permission' => 'reports.approvals.view', 'posted_only' => false,
            'columns' => [
                'requested_at' => 'تاريخ الطلب', 'document_number' => 'المستند',
                'module' => 'الموديول', 'branch' => 'الفرع', 'status' => 'الحالة',
                'age_hours' => 'العمر بالساعات', 'amount' => 'المبلغ',
            ],
        ],
        'audit' => [
            'name' => 'تقرير التدقيق', 'module' => 'audit',
            'permission' => 'reports.audit.view', 'posted_only' => false, 'sensitive' => true,
            'columns' => [
                'occurred_at' => 'التاريخ', 'module' => 'الموديول', 'action' => 'الإجراء',
                'document_number' => 'المستند', 'actor' => 'المستخدم',
                'branch' => 'الفرع', 'correlation_id' => 'Correlation ID',
            ],
        ],
    ];

    public function all(): array
    {
        return collect(self::DEFINITIONS)->map(
            fn (array $definition, string $code) => $this->enrich($code, $definition)
        )->all();
    }

    public function get(string $code): array
    {
        if (! isset(self::DEFINITIONS[$code])) {
            throw new InvalidArgumentException("Unknown report [{$code}].");
        }

        return $this->enrich($code, self::DEFINITIONS[$code]);
    }

    private function enrich(string $code, array $definition): array
    {
        return $definition + [
            'code' => $code,
            'allowed_filters' => [
                'date_from', 'date_to', 'branch_id', 'branch_ids', 'currency_id',
                'customer_id', 'supplier_id', 'employee_id', 'product_id',
                'warehouse_id', 'status', 'movement_days',
            ],
            'sort_fields' => array_keys($definition['columns']),
            'default_sort' => array_key_first($definition['columns']),
            'exports' => ['csv', 'xlsx', 'print', 'pdf'],
            'company_scope' => true,
            'branch_scope' => true,
            'currency_behavior' => 'document_or_company_currency_without_automatic_conversion',
            'data_source' => match ($code) {
                'financial' => 'posted journal entries and lines',
                'sales' => 'posted sales invoices and credit notes',
                'receivables' => 'issued invoice balances and payment allocations',
                'purchases' => 'posted supplier invoices, credit notes and purchase orders',
                'payables' => 'supplier invoice balances and payment allocations',
                'inventory' => 'official stock balances and costing snapshots',
                'treasury' => 'posted general-ledger cash and bank lines',
                'employee-finance' => 'employee accruals, settlements, claims and advances',
                'approvals' => 'central approval tasks and actions',
                'audit' => 'immutable unified audit events',
            },
            'max_date_range_days' => 366,
            'export_row_limit' => 5000,
            'sensitive' => false,
        ];
    }
}
