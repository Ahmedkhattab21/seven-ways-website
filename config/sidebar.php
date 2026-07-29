<?php

return [
    [
        'key' => 'main',
        'label' => 'الرئيسية',
        'icon' => 'grid',
        'items' => [
            ['label' => 'لوحة التحكم', 'icon' => 'grid', 'route' => 'dashboard', 'permission' => 'dashboard.view'],
        ],
    ],
    [
        'key' => 'sales',
        'label' => 'المبيعات',
        'icon' => 'sales',
        'items' => [
            ['label' => 'العملاء', 'icon' => 'users', 'route' => 'customers.index', 'permission' => 'customers.view'],
            ['label' => 'السيارات', 'icon' => 'car', 'route' => 'vehicles.index', 'permission' => 'vehicles.view'],
            [
                'label' => 'المنتجات والخدمات',
                'icon' => 'box',
                'route' => 'catalog.index',
                'permissions_any' => ['products.view', 'services.view', 'service_packages.view'],
                'active' => [
                    'catalog.*',
                    'products.*',
                    'services.*',
                    'service-categories.*',
                    'service-packages.*',
                    'product-references.*',
                ],
            ],
            ['label' => 'عروض الأسعار', 'icon' => 'sales', 'route' => 'quotations.index', 'permission' => 'quotations.view'],
            ['label' => 'فواتير المبيعات', 'icon' => 'clipboard', 'route' => 'sales-invoices.index', 'permission' => 'sales_invoices.view'],
            ['label' => 'استلام المدفوعات', 'icon' => 'wallet', 'route' => 'customer-payments.index', 'permission' => 'customer_payments.view'],
            ['label' => 'مرتجعات المبيعات', 'icon' => 'box', 'route' => 'sales-credit-notes.index', 'permission' => 'sales_credit_notes.view'],
        ],
    ],
    [
        'key' => 'purchasing_inventory',
        'label' => 'المشتريات والمخزون',
        'icon' => 'box',
        'items' => [
            ['label' => 'الموردون', 'icon' => 'users', 'route' => 'suppliers.index', 'permission' => 'suppliers.view'],
            ['label' => 'أوامر الشراء', 'icon' => 'clipboard', 'route' => 'purchase-orders.index', 'permission' => 'purchase_orders.view'],
            ['label' => 'استلام المشتريات', 'icon' => 'box', 'route' => 'goods-receipts.index', 'permission' => 'goods_receipts.view'],
            ['label' => 'فواتير الموردين', 'icon' => 'clipboard', 'route' => 'supplier-invoices.index', 'permission' => 'supplier_invoices.view'],
            ['label' => 'المخازن', 'icon' => 'box', 'route' => 'warehouses.index', 'permission' => 'warehouses.view'],
            ['label' => 'أرصدة المخزون', 'icon' => 'box', 'route' => 'inventory.index', 'params' => ['balances'], 'permission' => 'inventory.view'],
            ['label' => 'حركات المخزون', 'icon' => 'box', 'route' => 'inventory.index', 'params' => ['movements'], 'permission' => 'inventory.view'],
            ['label' => 'الجرد والتسويات', 'icon' => 'clipboard', 'route' => 'inventory.index', 'params' => ['counts'], 'permission' => 'inventory.view'],
        ],
    ],
    [
        'key' => 'finance',
        'label' => 'المالية',
        'icon' => 'wallet',
        'items' => [
            ['label' => 'الخزائن', 'icon' => 'wallet', 'route' => 'treasury.cash-boxes.index', 'permission' => 'treasury.cash_boxes.view'],
            ['label' => 'المقبوضات', 'icon' => 'wallet', 'route' => 'treasury.cash-receipts.index', 'permission' => 'treasury.cash_receipts.view'],
            ['label' => 'المدفوعات', 'icon' => 'wallet', 'route' => 'treasury.cash-payments.index', 'permission' => 'treasury.cash_payments.view'],
            ['label' => 'البنوك', 'icon' => 'building', 'route' => 'treasury.bank-accounts.index', 'permission' => 'treasury.bank_accounts.view'],
            ['label' => 'دليل الحسابات', 'icon' => 'chart', 'route' => 'accounting.accounts.index', 'permission' => 'accounting.accounts.view'],
            ['label' => 'القيود اليومية', 'icon' => 'clipboard', 'route' => 'accounting.journals.index', 'permission' => 'accounting.journals.view'],
            ['label' => 'التقارير المالية', 'icon' => 'chart', 'route' => 'accounting.reports.trial-balance', 'permission' => 'accounting.trial_balance.view'],
        ],
    ],
    [
        'key' => 'settings',
        'label' => 'الإعدادات',
        'icon' => 'settings',
        'items' => [
            ['label' => 'بيانات الشركة', 'icon' => 'building', 'route' => 'company.edit', 'permission' => 'companies.view'],
            ['label' => 'الفروع', 'icon' => 'building', 'route' => 'branches.index', 'permission' => 'branches.view'],
            ['label' => 'المستخدمون', 'icon' => 'users', 'route' => 'users.index', 'permission' => 'users.view'],
            ['label' => 'الأدوار والصلاحيات', 'icon' => 'settings', 'route' => 'roles.index', 'permission' => 'roles.view'],
            ['label' => 'الضرائب', 'icon' => 'settings', 'route' => 'reference.index', 'params' => ['taxes'], 'permission' => 'taxes.view'],
            ['label' => 'العملات', 'icon' => 'settings', 'route' => 'reference.index', 'params' => ['currencies'], 'permission' => 'settings.view'],
            ['label' => 'طرق الدفع', 'icon' => 'settings', 'route' => 'reference.index', 'params' => ['payment-methods'], 'permission' => 'payment_methods.view'],
            ['label' => 'تسلسل المستندات', 'icon' => 'clipboard', 'route' => 'reference.index', 'params' => ['document-sequences'], 'permission' => 'document_sequences.view'],
            ['label' => 'إعدادات المحاسبة', 'icon' => 'settings', 'route' => 'accounting.settings.edit', 'permission' => 'accounting.settings.view'],
        ],
    ],
];
