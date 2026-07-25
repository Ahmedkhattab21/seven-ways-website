@php
    $futureNavigation = [
        ['label' => 'المبيعات', 'icon' => 'sales'],
        ['label' => 'الحجوزات وأوامر العمل', 'icon' => 'clipboard'],
        ['label' => 'الخدمات', 'icon' => 'wrench'],
        ['label' => 'المنتجات والمخزون', 'icon' => 'box'],
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
