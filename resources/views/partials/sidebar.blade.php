@php
    $futureNavigation = [
        ['label' => 'المبيعات', 'icon' => 'sales'],
        ['label' => 'العملاء والسيارات', 'icon' => 'users'],
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
        @if(auth()->user()->hasPermission('branches.view'))<a class="sw-nav-item @if(request()->routeIs('branches.*')) sw-nav-item--active @endif" href="{{ route('branches.index') }}"><x-icon name="building" /><span>الفروع</span></a>@endif
        @if(auth()->user()->hasPermission('users.view'))<a class="sw-nav-item @if(request()->routeIs('users.*')) sw-nav-item--active @endif" href="{{ route('users.index') }}"><x-icon name="users" /><span>المستخدمون</span></a>@endif
        @if(auth()->user()->hasPermission('roles.view'))<a class="sw-nav-item @if(request()->routeIs('roles.*')) sw-nav-item--active @endif" href="{{ route('roles.index') }}"><x-icon name="settings" /><span>الأدوار والصلاحيات</span></a>@endif
        @if(auth()->user()->hasPermission('companies.view'))<a class="sw-nav-item @if(request()->routeIs('company.*')) sw-nav-item--active @endif" href="{{ route('company.edit') }}"><x-icon name="settings" /><span>بيانات الشركة</span></a>@endif
        <p class="sw-sidebar__label">الموديولات القادمة</p>
        @foreach($futureNavigation as $item)<span class="sw-nav-item sw-nav-item--disabled" aria-disabled="true"><x-icon :name="$item['icon']" /><span>{{ $item['label'] }}</span><small>قريبًا</small></span>@endforeach
    </nav>
    <div class="sw-sidebar__footer"><div class="sw-sidebar__status"><span class="sw-status-dot"></span><div><strong>النظام متصل</strong><small>الأساس متعدد الفروع</small></div></div></div>
</aside>
