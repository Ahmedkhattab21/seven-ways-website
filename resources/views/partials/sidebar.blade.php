@php
    $futureNavigation = [
        ['label' => 'الحجوزات وأوامر العمل', 'icon' => 'clipboard'],
        ['label' => 'المشتريات', 'icon' => 'cart'],
        ['label' => 'الحسابات', 'icon' => 'wallet'],
        ['label' => 'الموظفون', 'icon' => 'users'],
        ['label' => 'التقارير', 'icon' => 'chart'],
    ];
@endphp
<aside class="sw-sidebar" id="app-sidebar" data-sidebar aria-label="التنقل الرئيسي">
    <div class="sw-sidebar__header"><x-brand /><button class="sw-icon-button sw-sidebar__close" type="button" data-sidebar-close aria-label="إغلاق القائمة"><x-icon name="close" /></button></div>
    <nav class="sw-sidebar__nav">
        <p class="sw-sidebar__label">مساحة العمل</p>
        <a class="sw-nav-item @if(request()->routeIs('dashboard')) sw-nav-item--active @endif" href="{{ route('dashboard') }}"><x-icon name="dashboard" /><span>الرئيسية</span></a>
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
        <p class="sw-sidebar__label">الموديولات القادمة</p>
        @foreach($futureNavigation as $item)<span class="sw-nav-item sw-nav-item--disabled" aria-disabled="true"><x-icon :name="$item['icon']" /><span>{{ $item['label'] }}</span><small>قريبًا</small></span>@endforeach
    </nav>
    <div class="sw-sidebar__footer"><div class="sw-sidebar__status"><span class="sw-status-dot"></span><div><strong>النظام متصل</strong><small>الأساس متعدد الفروع</small></div></div></div>
</aside>
