@php
    $futureNavigation = [
        ['label' => 'الحجوزات وأوامر العمل', 'icon' => 'clipboard'],
        ['label' => 'المشتريات', 'icon' => 'cart'],
        ['label' => 'الحسابات', 'icon' => 'wallet'],
        ['label' => 'الموظفون', 'icon' => 'users'],
    ];
    $futureNavigation = array_values(array_filter($futureNavigation, fn ($item) => $item['icon'] !== 'cart'));
    $futureNavigation = array_values(array_filter($futureNavigation, fn ($item) => $item['icon'] !== 'wallet'));
@endphp
<aside class="sw-sidebar" id="app-sidebar" data-sidebar aria-label="التنقل الرئيسي">
    <div class="sw-sidebar__header"><x-brand /><button class="sw-icon-button sw-sidebar__close" type="button" data-sidebar-close aria-label="إغلاق القائمة"><x-icon name="close" /></button></div>
    <nav class="sw-sidebar__nav">
        <p class="sw-sidebar__label">مساحة العمل</p>
        <a class="sw-nav-item @if(request()->routeIs('dashboard')) sw-nav-item--active @endif" href="{{ route('dashboard') }}"><x-icon name="dashboard" /><span>الرئيسية</span></a>
        @if(auth()->user()->hasPermission('dashboards.executive.view'))<a class="sw-nav-item @if(request()->routeIs('dashboards.executive')) sw-nav-item--active @endif" href="{{ route('dashboards.executive') }}"><x-icon name="chart" /><span>المؤشرات التنفيذية</span></a>@endif
        @if(auth()->user()->hasPermission('dashboards.branch.view'))<a class="sw-nav-item @if(request()->routeIs('dashboards.branches')) sw-nav-item--active @endif" href="{{ route('dashboards.branches') }}"><x-icon name="building" /><span>مؤشرات الفروع</span></a>@endif
        @if(collect(['financial','sales','purchases','inventory','receivables','payables','treasury','employee_finance','approvals','audit'])->contains(fn($module) => auth()->user()->hasPermission('reports.'.$module.'.view')))
            <a class="sw-nav-item @if(request()->routeIs('analytics.reports.*')) sw-nav-item--active @endif" href="{{ route('analytics.reports.show', collect(['financial','sales','purchases','inventory','receivables','payables','treasury','employee-finance','approvals','audit'])->first(fn($module) => auth()->user()->hasPermission('reports.'.str_replace('-','_',$module).'.view'))) }}"><x-icon name="chart" /><span>مركز التقارير</span></a>
        @endif
        @if(auth()->user()->hasPermission('approvals.view'))<a class="sw-nav-item @if(request()->routeIs('approvals.*')) sw-nav-item--active @endif" href="{{ route('approvals.index') }}"><x-icon name="clipboard" /><span>صندوق الاعتمادات</span></a>@endif
        @if(auth()->user()->hasPermission('approvals.view'))<a class="sw-nav-item @if(request()->routeIs('approvals.reports')) sw-nav-item--active @endif" href="{{ route('approvals.reports') }}"><x-icon name="chart" /><span>تقارير الاعتمادات</span></a>@endif
        @if(auth()->user()->hasPermission('delegations.view'))<a class="sw-nav-item @if(request()->routeIs('delegations.*')) sw-nav-item--active @endif" href="{{ route('delegations.index') }}"><x-icon name="users" /><span>تفويضات الاعتماد</span></a>@endif
        @if(auth()->user()->hasPermission('audit.view'))<a class="sw-nav-item @if(request()->routeIs('audit.*')) sw-nav-item--active @endif" href="{{ route('audit.index') }}"><x-icon name="chart" /><span>سجل التدقيق</span></a>@endif
        @if(auth()->user()->hasPermission('customers.view'))<a class="sw-nav-item @if(request()->routeIs('customers.*')) sw-nav-item--active @endif" href="{{ route('customers.index') }}"><x-icon name="users" /><span>العملاء</span></a>@endif
        @if(auth()->user()->hasPermission('vehicles.view'))<a class="sw-nav-item @if(request()->routeIs('vehicles.*')) sw-nav-item--active @endif" href="{{ route('vehicles.index') }}"><x-icon name="wrench" /><span>السيارات</span></a>@endif
        @if(auth()->user()->hasPermission('leads.view'))<a class="sw-nav-item @if(request()->routeIs('leads.*')) sw-nav-item--active @endif" href="{{ route('leads.index') }}"><x-icon name="sales" /><span>العملاء المحتملون</span></a>@endif
        @if(auth()->user()->hasPermission('products.view'))<a class="sw-nav-item @if(request()->routeIs('products.*')) sw-nav-item--active @endif" href="{{ route('products.index') }}"><x-icon name="box" /><span>المنتجات</span></a>@endif
        @if(auth()->user()->hasPermission('product_categories.manage'))<a class="sw-nav-item" href="{{ route('product-references.index', 'categories') }}"><x-icon name="box" /><span>تصنيفات المنتجات</span></a>@endif
        @if(auth()->user()->hasPermission('product_brands.manage'))<a class="sw-nav-item" href="{{ route('product-references.index', 'brands') }}"><x-icon name="box" /><span>العلامات التجارية</span></a>@endif
        @if(auth()->user()->hasPermission('warehouses.view'))<a class="sw-nav-item @if(request()->routeIs('warehouses.*')) sw-nav-item--active @endif" href="{{ route('warehouses.index') }}"><x-icon name="box" /><span>المخازن</span></a>@endif
        @if(auth()->user()->hasPermission('stock_transfers.view'))<a class="sw-nav-item @if(request()->routeIs('stock-transfers.*')) sw-nav-item--active @endif" href="{{ route('stock-transfers.index') }}"><x-icon name="box" /><span>تحويلات المخزون</span></a>@endif
        @if(auth()->user()->hasPermission('service_categories.view'))<a class="sw-nav-item @if(request()->routeIs('service-categories.*')) sw-nav-item--active @endif" href="{{ route('service-categories.index') }}"><x-icon name="wrench" /><span>تصنيفات الخدمات</span></a>@endif
        @if(auth()->user()->hasPermission('services.view'))<a class="sw-nav-item @if(request()->routeIs('services.*')) sw-nav-item--active @endif" href="{{ route('services.index') }}"><x-icon name="wrench" /><span>الخدمات</span></a>@endif
        @if(auth()->user()->hasPermission('service_packages.view'))<a class="sw-nav-item @if(request()->routeIs('service-packages.*')) sw-nav-item--active @endif" href="{{ route('service-packages.index') }}"><x-icon name="box" /><span>باقات الخدمات</span></a>@endif
        @if(auth()->user()->hasPermission('promotions.view'))<a class="sw-nav-item @if(request()->routeIs('promotions.*')) sw-nav-item--active @endif" href="{{ route('promotions.index') }}"><x-icon name="sales" /><span>العروض الترويجية</span></a>@endif
        @if(auth()->user()->hasPermission('quotations.view'))<a class="sw-nav-item @if(request()->routeIs('quotations.*')) sw-nav-item--active @endif" href="{{ route('quotations.index') }}"><x-icon name="sales" /><span>عروض الأسعار</span></a>@endif
        @if(auth()->user()->hasPermission('appointments.view'))<a class="sw-nav-item @if(request()->routeIs('appointments.index','appointments.show','appointments.create','appointments.edit')) sw-nav-item--active @endif" href="{{ route('appointments.index') }}"><x-icon name="clipboard" /><span>الحجوزات</span></a>@endif
        @if(auth()->user()->hasPermission('appointments.calendar'))<a class="sw-nav-item @if(request()->routeIs('appointments.calendar')) sw-nav-item--active @endif" href="{{ route('appointments.calendar') }}"><x-icon name="chart" /><span>تقويم الحجوزات</span></a>@endif
        @if(auth()->user()->hasPermission('work_orders.view'))<a class="sw-nav-item @if(request()->routeIs('work-orders.*','work-order-services.*','vehicle-inspections.*')) sw-nav-item--active @endif" href="{{ route('work-orders.index') }}"><x-icon name="clipboard" /><span>أوامر العمل</span></a>@endif
        @if(auth()->user()->hasPermission('quality_checks.view'))<a class="sw-nav-item @if(request()->routeIs('quality-checks.*','quality-templates.*')) sw-nav-item--active @endif" href="{{ route('quality-checks.index') }}"><x-icon name="clipboard" /><span>فحص الجودة</span></a>@endif
        @if(auth()->user()->hasPermission('rework_orders.view'))<a class="sw-nav-item @if(request()->routeIs('rework-orders.*')) sw-nav-item--active @endif" href="{{ route('rework-orders.index') }}"><x-icon name="wrench" /><span>إعادة العمل</span></a>@endif
        @if(auth()->user()->hasPermission('work_orders.deliver'))<a class="sw-nav-item @if(request()->routeIs('deliveries.*')) sw-nav-item--active @endif" href="{{ route('deliveries.index') }}"><x-icon name="clipboard" /><span>تسليم السيارات</span></a>@endif
        @if(auth()->user()->hasPermission('warranties.view'))<a class="sw-nav-item @if(request()->routeIs('warranties.*')) sw-nav-item--active @endif" href="{{ route('warranties.index') }}"><x-icon name="clipboard" /><span>الضمانات</span></a>@endif
        @if(auth()->user()->hasPermission('warranty_claims.view'))<a class="sw-nav-item @if(request()->routeIs('warranty-claims.*')) sw-nav-item--active @endif" href="{{ route('warranty-claims.index') }}"><x-icon name="clipboard" /><span>مطالبات الضمان</span></a>@endif
        @if(auth()->user()->hasPermission('sales_invoices.view'))<a class="sw-nav-item @if(request()->routeIs('sales-invoices.*')) sw-nav-item--active @endif" href="{{ route('sales-invoices.index') }}"><x-icon name="sales" /><span>فواتير المبيعات</span></a>@endif
        @if(auth()->user()->hasPermission('customer_payments.view'))<a class="sw-nav-item @if(request()->routeIs('customer-payments.*')) sw-nav-item--active @endif" href="{{ route('customer-payments.index') }}"><x-icon name="wallet" /><span>المدفوعات</span></a>@endif
        @if(auth()->user()->hasPermission('sales_credit_notes.view'))<a class="sw-nav-item @if(request()->routeIs('sales-credit-notes.*')) sw-nav-item--active @endif" href="{{ route('sales-credit-notes.index') }}"><x-icon name="clipboard" /><span>الإشعارات الدائنة</span></a>@endif
        @if(auth()->user()->hasPermission('customer_refunds.view'))<a class="sw-nav-item @if(request()->routeIs('customer-refunds.*')) sw-nav-item--active @endif" href="{{ route('customer-refunds.index') }}"><x-icon name="wallet" /><span>المبالغ المستردة</span></a>@endif
        @if(auth()->user()->hasPermission('accounts_receivable.aging'))<a class="sw-nav-item @if(request()->routeIs('sales-reports.*')) sw-nav-item--active @endif" href="{{ route('sales-reports.aging') }}"><x-icon name="chart" /><span>أعمار الديون</span></a>@endif
        @if(auth()->user()->hasPermission('suppliers.view'))<a class="sw-nav-item @if(request()->routeIs('suppliers.*')) sw-nav-item--active @endif" href="{{ route('suppliers.index') }}"><x-icon name="users" /><span>الموردون</span></a>@endif
        @if(auth()->user()->hasPermission('purchase_requisitions.view'))<a class="sw-nav-item @if(request()->routeIs('purchase-requisitions.*')) sw-nav-item--active @endif" href="{{ route('purchase-requisitions.index') }}"><x-icon name="clipboard" /><span>طلبات الشراء</span></a>@endif
        @if(auth()->user()->hasPermission('purchase_orders.view'))<a class="sw-nav-item @if(request()->routeIs('purchase-orders.*')) sw-nav-item--active @endif" href="{{ route('purchase-orders.index') }}"><x-icon name="cart" /><span>أوامر الشراء</span></a>@endif
        @if(auth()->user()->hasPermission('goods_receipts.view'))<a class="sw-nav-item @if(request()->routeIs('goods-receipts.*')) sw-nav-item--active @endif" href="{{ route('goods-receipts.index') }}"><x-icon name="box" /><span>استلام المشتريات</span></a>@endif
        @if(auth()->user()->hasPermission('purchase_returns.view'))<a class="sw-nav-item @if(request()->routeIs('purchase-returns.*')) sw-nav-item--active @endif" href="{{ route('purchase-returns.index') }}"><x-icon name="box" /><span>مرتجعات المشتريات</span></a>@endif
        @if(auth()->user()->hasPermission('supplier_invoices.view'))<a class="sw-nav-item @if(request()->routeIs('supplier-invoices.*')) sw-nav-item--active @endif" href="{{ route('supplier-invoices.index') }}"><x-icon name="clipboard" /><span>فواتير الموردين</span></a>@endif
        @if(auth()->user()->hasPermission('supplier_payments.view'))<a class="sw-nav-item @if(request()->routeIs('supplier-payments.*')) sw-nav-item--active @endif" href="{{ route('supplier-payments.index') }}"><x-icon name="wallet" /><span>دفعات الموردين</span></a>@endif
        @if(auth()->user()->hasPermission('supplier_credit_notes.view'))<a class="sw-nav-item @if(request()->routeIs('supplier-credit-notes.*')) sw-nav-item--active @endif" href="{{ route('supplier-credit-notes.index') }}"><x-icon name="clipboard" /><span>إشعارات الموردين الدائنة</span></a>@endif
        @if(auth()->user()->hasPermission('accounts_payable.aging'))<a class="sw-nav-item @if(request()->routeIs('purchasing-reports.*')) sw-nav-item--active @endif" href="{{ route('purchasing-reports.aging') }}"><x-icon name="chart" /><span>أعمار الديون الدائنة</span></a>@endif
        @if(auth()->user()->hasPermission('inventory.view'))
            @foreach(['balances'=>'الأرصدة','movements'=>'حركات المخزون','rolls'=>'الرولات','scraps'=>'القصاصات','openings'=>'الرصيد الافتتاحي','adjustments'=>'التسويات','counts'=>'الجرد','alerts'=>'التنبيهات'] as $section=>$label)
                <a class="sw-nav-item @if(request()->routeIs('inventory.*') && request()->route('section') === $section) sw-nav-item--active @endif" href="{{ route('inventory.index', $section) }}"><x-icon name="box" /><span>{{ $label }}</span></a>
            @endforeach
        @endif
        @if(auth()->user()->hasPermission('branches.view'))<a class="sw-nav-item @if(request()->routeIs('branches.*')) sw-nav-item--active @endif" href="{{ route('branches.index') }}"><x-icon name="building" /><span>الفروع</span></a>@endif
        @if(auth()->user()->hasPermission('users.view'))<a class="sw-nav-item @if(request()->routeIs('users.*')) sw-nav-item--active @endif" href="{{ route('users.index') }}"><x-icon name="users" /><span>المستخدمون</span></a>@endif
        @if(auth()->user()->hasPermission('roles.view'))<a class="sw-nav-item @if(request()->routeIs('roles.*')) sw-nav-item--active @endif" href="{{ route('roles.index') }}"><x-icon name="settings" /><span>الأدوار والصلاحيات</span></a>@endif
        @if(auth()->user()->hasPermission('companies.view'))<a class="sw-nav-item @if(request()->routeIs('company.*')) sw-nav-item--active @endif" href="{{ route('company.edit') }}"><x-icon name="settings" /><span>بيانات الشركة</span></a>@endif
        @if(auth()->user()->hasPermission('branch_settings.view'))<a class="sw-nav-item @if(request()->routeIs('branch-settings.*')) sw-nav-item--active @endif" href="{{ route('branch-settings.edit') }}"><x-icon name="settings" /><span>إعدادات الفرع</span></a>@endif
        @if(auth()->user()->hasPermission('employees.view'))
            <a class="sw-nav-item @if(request()->routeIs('employee-finance.*')) sw-nav-item--active @endif" href="{{ route('employee-finance.index') }}"><x-icon name="users" /><span>مالية الموظفين</span></a>
        @endif
        @php
            $referenceLinks = [
                'currencies' => ['settings.view', 'العملات'],
                'taxes' => ['taxes.view', 'الضرائب'],
                'units' => ['units.view', 'الوحدات'],
                'payment-methods' => ['payment_methods.view', 'طرق الدفع'],
                'vehicle-brands' => ['vehicle_references.view', 'ماركات السيارات'],
                'vehicle-models' => ['vehicle_references.view', 'موديلات السيارات'],
                'vehicle-sizes' => ['vehicle_references.view', 'أحجام السيارات'],
                'vehicle-types' => ['vehicle_references.view', 'أنواع السيارات'],
                'fiscal-years' => ['fiscal_years.view', 'السنوات المالية'],
                'document-sequences' => ['document_sequences.view', 'تسلسل المستندات'],
            ];
        @endphp
        @foreach($referenceLinks as $section => [$permission, $label])
            @if(auth()->user()->hasRole('system_admin') || auth()->user()->hasPermission($permission))
                <a class="sw-nav-item @if(request()->routeIs('reference.*') && request()->route('section') === $section) sw-nav-item--active @endif" href="{{ route('reference.index', $section) }}"><x-icon name="settings" /><span>{{ $label }}</span></a>
            @endif
        @endforeach
        @if(auth()->user()->hasPermission('accounting.accounts.view'))
            <p class="sw-sidebar__label">المحاسبة</p>
            <a class="sw-nav-item @if(request()->routeIs('accounting.dashboard')) sw-nav-item--active @endif" href="{{ route('accounting.dashboard') }}"><x-icon name="wallet" /><span>نظرة عامة</span></a>
            <a class="sw-nav-item @if(request()->routeIs('accounting.accounts.*')) sw-nav-item--active @endif" href="{{ route('accounting.accounts.index') }}"><x-icon name="chart" /><span>دليل الحسابات</span></a>
            @if(auth()->user()->hasPermission('accounting.fiscal_years.view'))<a class="sw-nav-item @if(request()->routeIs('accounting.fiscal-years.*')) sw-nav-item--active @endif" href="{{ route('accounting.fiscal-years.index') }}"><x-icon name="clipboard" /><span>السنوات المالية</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.periods.view'))<a class="sw-nav-item @if(request()->routeIs('accounting.periods.*')) sw-nav-item--active @endif" href="{{ route('accounting.periods.index') }}"><x-icon name="clipboard" /><span>الفترات المحاسبية</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.cost_centers.view'))<a class="sw-nav-item @if(request()->routeIs('accounting.cost-centers.*')) sw-nav-item--active @endif" href="{{ route('accounting.cost-centers.index') }}"><x-icon name="building" /><span>مراكز التكلفة</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.settings.view'))<a class="sw-nav-item @if(request()->routeIs('accounting.settings.*','accounting.branch-settings.*')) sw-nav-item--active @endif" href="{{ route('accounting.settings.edit') }}"><x-icon name="settings" /><span>إعدادات المحاسبة</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.posting_profiles.view'))<a class="sw-nav-item @if(request()->routeIs('accounting.posting-profiles.*')) sw-nav-item--active @endif" href="{{ route('accounting.posting-profiles.index') }}"><x-icon name="clipboard" /><span>قوالب الترحيل</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.opening_balances.view'))<a class="sw-nav-item @if(request()->routeIs('accounting.opening-balances.*')) sw-nav-item--active @endif" href="{{ route('accounting.opening-balances.index') }}"><x-icon name="wallet" /><span>الأرصدة الافتتاحية</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.journals.view'))<a class="sw-nav-item @if(request()->routeIs('accounting.journals.*')) sw-nav-item--active @endif" href="{{ route('accounting.journals.index') }}"><x-icon name="clipboard" /><span>القيود اليومية</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.posting.execute'))<a class="sw-nav-item @if(request()->routeIs('accounting.posting.*')) sw-nav-item--active @endif" href="{{ route('accounting.posting.index') }}"><x-icon name="wallet" /><span>الترحيل المحاسبي</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.mappings.payment_methods') || auth()->user()->hasPermission('accounting.mappings.products'))<a class="sw-nav-item @if(request()->routeIs('accounting.mappings.*')) sw-nav-item--active @endif" href="{{ route('accounting.mappings.index') }}"><x-icon name="settings" /><span>ربط الحسابات</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.general_ledger.view'))<a class="sw-nav-item @if(request()->routeIs('accounting.reports.general-ledger')) sw-nav-item--active @endif" href="{{ route('accounting.reports.general-ledger') }}"><x-icon name="chart" /><span>الأستاذ العام</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.general_journal.view'))<a class="sw-nav-item" href="{{ route('accounting.reports.general-journal') }}"><x-icon name="clipboard" /><span>استعلام القيود</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.trial_balance.view'))<a class="sw-nav-item" href="{{ route('accounting.reports.trial-balance') }}"><x-icon name="chart" /><span>ميزان المراجعة</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.income_statement.view'))<a class="sw-nav-item" href="{{ route('accounting.reports.income-statement') }}"><x-icon name="wallet" /><span>قائمة الدخل</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.balance_sheet.view'))<a class="sw-nav-item" href="{{ route('accounting.reports.balance-sheet') }}"><x-icon name="wallet" /><span>الميزانية العمومية</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.cash_flow.view'))<a class="sw-nav-item" href="{{ route('accounting.reports.cash-flow') }}"><x-icon name="wallet" /><span>التدفقات النقدية</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.cost_center_reports.view'))<a class="sw-nav-item" href="{{ route('accounting.reports.cost-centers') }}"><x-icon name="building" /><span>تقارير مراكز التكلفة</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.branch_reports.view'))<a class="sw-nav-item" href="{{ route('accounting.reports.branches') }}"><x-icon name="building" /><span>تقارير الفروع</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.unposted_sources.view'))<a class="sw-nav-item" href="{{ route('accounting.reports.unposted-sources') }}"><x-icon name="clipboard" /><span>المستندات غير المرحلة</span></a>@endif
            @if(auth()->user()->hasPermission('accounting.closing.view'))<a class="sw-nav-item @if(request()->routeIs('accounting.closing.*')) sw-nav-item--active @endif" href="{{ route('accounting.closing.index') }}"><x-icon name="lock" /><span>الإقفال المحاسبي</span></a>@endif
        @endif
        @if(auth()->user()->hasPermission('treasury.balances.view') || auth()->user()->hasPermission('treasury.bank_accounts.view') || auth()->user()->hasPermission('treasury.cash_boxes.view') || auth()->user()->hasPermission('treasury.bank_statements.view') || auth()->user()->hasPermission('treasury.reconciliation.view'))
            <p class="sw-sidebar__label">الخزينة والبنوك</p>
            @if(auth()->user()->hasPermission('treasury.balances.view'))<a class="sw-nav-item @if(request()->routeIs('treasury.dashboard')) sw-nav-item--active @endif" href="{{ route('treasury.dashboard') }}"><x-icon name="wallet" /><span>نظرة عامة</span></a>@endif
            @if(auth()->user()->hasPermission('treasury.banks.view'))<a class="sw-nav-item @if(request()->routeIs('treasury.banks.*')) sw-nav-item--active @endif" href="{{ route('treasury.banks.index') }}"><x-icon name="building" /><span>البنوك</span></a>@endif
            @if(auth()->user()->hasPermission('treasury.bank_accounts.view'))<a class="sw-nav-item @if(request()->routeIs('treasury.bank-accounts.*')) sw-nav-item--active @endif" href="{{ route('treasury.bank-accounts.index') }}"><x-icon name="wallet" /><span>الحسابات البنكية</span></a>@endif
            @if(auth()->user()->hasPermission('treasury.cash_boxes.view'))<a class="sw-nav-item @if(request()->routeIs('treasury.cash-boxes.*')) sw-nav-item--active @endif" href="{{ route('treasury.cash-boxes.index') }}"><x-icon name="wallet" /><span>الخزائن وأمناؤها</span></a>@endif
            @if(auth()->user()->hasPermission('treasury.mappings.view'))<a class="sw-nav-item @if(request()->routeIs('treasury.mappings.*')) sw-nav-item--active @endif" href="{{ route('treasury.mappings.index') }}"><x-icon name="settings" /><span>ربط وسائل الدفع</span></a>@endif
            @if(auth()->user()->hasPermission('treasury.transfers.view'))<a class="sw-nav-item @if(request()->routeIs('treasury.transfers.*')) sw-nav-item--active @endif" href="{{ route('treasury.transfers.index') }}"><x-icon name="wallet" /><span>التحويلات</span></a>@endif
            @if(auth()->user()->hasPermission('treasury.bank_statements.view'))<a class="sw-nav-item @if(request()->routeIs('treasury.bank-statements.*')) sw-nav-item--active @endif" href="{{ route('treasury.bank-statements.index') }}"><x-icon name="clipboard" /><span>كشوف الحساب والاستيراد</span></a>@endif
            @if(auth()->user()->hasPermission('treasury.bank_statements.import'))<a class="sw-nav-item @if(request()->routeIs('treasury.bank-statement-profiles.*')) sw-nav-item--active @endif" href="{{ route('treasury.bank-statement-profiles.index') }}"><x-icon name="settings" /><span>ملفات تعريف الاستيراد</span></a>@endif
            @if(auth()->user()->hasPermission('treasury.reconciliation.view'))<a class="sw-nav-item @if(request()->routeIs('treasury.reconciliations.*')) sw-nav-item--active @endif" href="{{ route('treasury.reconciliations.index') }}"><x-icon name="chart" /><span>جلسات المطابقة</span></a>@endif
            @if(auth()->user()->hasPermission('treasury.matching_rules.view'))<a class="sw-nav-item @if(request()->routeIs('treasury.matching-rules.*')) sw-nav-item--active @endif" href="{{ route('treasury.matching-rules.index') }}"><x-icon name="settings" /><span>قواعد المطابقة</span></a>@endif
            @if(auth()->user()->hasPermission('treasury.bank_adjustments.view'))<a class="sw-nav-item @if(request()->routeIs('treasury.bank-adjustments.*')) sw-nav-item--active @endif" href="{{ route('treasury.bank-adjustments.index') }}"><x-icon name="clipboard" /><span>تسويات البنك</span></a>@endif
            @if(auth()->user()->hasPermission('treasury.reconciliation.view'))<a class="sw-nav-item @if(request()->routeIs('treasury.reconciliations.reports')) sw-nav-item--active @endif" href="{{ route('treasury.reconciliations.reports') }}"><x-icon name="chart" /><span>تقارير المطابقة</span></a>@endif
        @endif
        <p class="sw-sidebar__label">الموديولات القادمة</p>
        @foreach($futureNavigation as $item)<span class="sw-nav-item sw-nav-item--disabled" aria-disabled="true"><x-icon :name="$item['icon']" /><span>{{ $item['label'] }}</span><small>قريبًا</small></span>@endforeach
        @if(auth()->user()->hasPermission('treasury.cash_sessions.view'))<a class="sw-nav-item" href="{{ route('treasury.cash-sessions.index') }}"><x-icon name="wallet" /><span>جلسات الخزائن والجرد</span></a>@endif
        @if(auth()->user()->hasPermission('treasury.cash_receipts.view'))<a class="sw-nav-item" href="{{ route('treasury.cash-receipts.index') }}"><x-icon name="wallet" /><span>المقبوضات النقدية</span></a>@endif
        @if(auth()->user()->hasPermission('treasury.cash_payments.view'))<a class="sw-nav-item" href="{{ route('treasury.cash-payments.index') }}"><x-icon name="wallet" /><span>المدفوعات النقدية</span></a>@endif
        @if(auth()->user()->hasPermission('treasury.cheques.view'))<a class="sw-nav-item" href="{{ route('treasury.cheques.received') }}"><x-icon name="clipboard" /><span>الشيكات الواردة</span></a><a class="sw-nav-item" href="{{ route('treasury.cheques.issued') }}"><x-icon name="clipboard" /><span>الشيكات الصادرة</span></a>@endif
        @if(auth()->user()->hasPermission('treasury.merchant_settlements.view'))<a class="sw-nav-item" href="{{ route('treasury.merchant-settlements.index') }}"><x-icon name="wallet" /><span>تسويات نقاط البيع</span></a>@endif
        @if(auth()->user()->hasPermission('treasury.approval_limits.view'))<a class="sw-nav-item" href="{{ route('treasury.approval-limits.index') }}"><x-icon name="settings" /><span>حدود الاعتماد</span></a>@endif
        @if(auth()->user()->hasPermission('treasury.reports.view'))<a class="sw-nav-item" href="{{ route('treasury.operation-reports') }}"><x-icon name="chart" /><span>تقارير عمليات الخزينة</span></a>@endif
    </nav>
    <div class="sw-sidebar__footer"><div class="sw-sidebar__status"><span class="sw-status-dot"></span><div><strong>النظام متصل</strong><small>الأساس متعدد الفروع</small></div></div></div>
</aside>
