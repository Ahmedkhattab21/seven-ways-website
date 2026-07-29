@extends('layouts.app')
@section('title', 'المنتجات والخدمات')
@section('page-title', 'المنتجات والخدمات')
@section('breadcrumb', 'المنتجات والخدمات')
@section('page-description', 'إدارة المنتجات والخدمات والباقات والتصنيفات والأسعار من مكان واحد.')
@section('content')
<x-catalog-navigation active="overview" :permission-names="$permissionNames" />

<div class="catalog-quick-actions">
    @if($permissionNames->contains('products.create'))
        <a class="sw-button sw-button--primary" href="{{ route('products.create') }}">إضافة منتج</a>
    @endif
    @if($permissionNames->contains('services.create'))
        <a class="sw-button sw-button--primary" href="{{ route('services.create') }}">إضافة خدمة</a>
    @endif
    @if($permissionNames->contains('service_packages.create'))
        <a class="sw-button sw-button--primary" href="{{ route('service-packages.create') }}">+ إضافة باقة خدمات</a>
    @endif
    @if($permissionNames->contains('service_categories.manage'))
        <a class="sw-button" href="{{ route('service-categories.create') }}">إضافة تصنيف خدمة</a>
    @endif
    @if($permissionNames->contains('product_categories.manage'))
        <a class="sw-button" href="{{ route('product-references.index', 'categories') }}">إضافة تصنيف منتج</a>
    @endif
    @if($permissionNames->contains('product_brands.manage'))
        <a class="sw-button" href="{{ route('product-references.index', 'brands') }}">إضافة علامة تجارية</a>
    @endif
</div>

@if($branches->isNotEmpty())
    <x-card title="سياق أسعار الخدمات والباقات">
        <form method="GET" class="catalog-branch-filter">
            <x-form.select name="branch_id" label="الفرع">
                @foreach($branches as $availableBranch)
                    <option value="{{ $availableBranch->id }}" @selected($branch?->id === $availableBranch->id)>{{ $availableBranch->name }}</option>
                @endforeach
            </x-form.select>
            <x-button type="submit">عرض</x-button>
        </form>
    </x-card>
@endif

<div class="catalog-summary">
    @foreach([
        'products' => ['المنتجات', 'products.index'],
        'services' => ['الخدمات', 'services.index'],
        'packages' => ['باقات الخدمات', 'service-packages.index'],
        'serviceCategories' => ['تصنيفات الخدمات', 'service-categories.index'],
        'productCategories' => ['تصنيفات المنتجات', 'product-references.index', ['categories']],
        'productBrands' => ['العلامات التجارية', 'product-references.index', ['brands']],
    ] as $key => $item)
        @if($summary[$key] !== null)
            <a class="catalog-summary__card" href="{{ route($item[1], $item[2] ?? []) }}">
                <span>{{ $item[0] }}</span>
                <strong>{{ $summary[$key]['active'] }} / {{ $summary[$key]['total'] }}</strong>
                <small>نشط / إجمالي</small>
            </a>
        @endif
    @endforeach
</div>

@if($permissions['service_packages.view'])
    <section class="catalog-package-feature">
        <div>
            <span class="catalog-package-feature__eyebrow">موديول أساسي</span>
            <h2>باقات الخدمات</h2>
            <p>تجميع أكثر من خدمة داخل باقة واحدة بسعر ومدة وإتاحة خاصة بكل فرع.</p>
        </div>
        <dl>
            <div><dt>الباقات النشطة</dt><dd>{{ $summary['packages']['active'] }}</dd></div>
            <div><dt>إجمالي الباقات</dt><dd>{{ $summary['packages']['total'] }}</dd></div>
            <div><dt>الباقات غير المسعّرة</dt><dd>{{ $summary['packages']['unpriced'] }}</dd></div>
        </dl>
        <div>
            <strong>آخر الباقات المضافة</strong>
            <p>{{ $packages->take(3)->pluck('name')->join('، ') ?: 'لا توجد باقات خدمات حتى الآن.' }}</p>
        </div>
        <div class="catalog-package-feature__actions">
            <a class="sw-button" href="{{ route('service-packages.index') }}">عرض باقات الخدمات</a>
            @if($permissionNames->contains('service_packages.create'))
                <a class="sw-button sw-button--primary" href="{{ route('service-packages.create') }}">إضافة باقة خدمات</a>
            @endif
        </div>
    </section>
@endif

@if($permissions['products.view'])
    <x-table-shell title="أحدث المنتجات" description="سعر البيع الافتراضي فقط؛ لا تُعرض تكلفة المنتج هنا.">
        <x-slot:tools><a href="{{ route('products.index') }}">عرض الكل</a></x-slot:tools>
        <thead><tr><th>SKU</th><th>المنتج</th><th>التصنيف</th><th>العلامة</th><th>سعر البيع</th><th>وحدة البيع</th><th>قابل للبيع</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        @forelse($products as $product)
            <tr>
                <td>{{ $product->sku }}</td>
                <td>{{ $product->name }}</td>
                <td>{{ $product->category?->name ?? '—' }}</td>
                <td>{{ $product->brand?->name ?? '—' }}</td>
                <td>{{ $product->default_sale_price !== null ? number_format((float) $product->default_sale_price, 2) : 'غير مسعّر' }}</td>
                <td>{{ $product->saleUnit?->symbol ?? $product->saleUnit?->name ?? '—' }}</td>
                <td>{{ $product->is_sellable ? 'نعم' : 'لا' }}</td>
                <td><x-status-badge :status="$product->is_active ? 'active' : 'inactive'" /></td>
                <td>@if($permissionNames->contains('products.update'))<a href="{{ route('products.edit', $product) }}">تعديل</a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="9">لا توجد منتجات حتى الآن.</td></tr>
        @endforelse
        </tbody>
    </x-table-shell>
@endif

@if($permissions['services.view'])
    <x-table-shell title="أحدث الخدمات" :description="$branch ? 'الأسعار والتوفر للفرع: '.$branch->name : 'لا يوجد فرع متاح لعرض الأسعار.'">
        <x-slot:tools><a href="{{ route('services.index') }}">عرض الكل</a></x-slot:tools>
        <thead><tr><th>الكود</th><th>الخدمة</th><th>التصنيف</th><th>السعر</th><th>الحد الأدنى</th><th>المصدر</th><th>المدة</th><th>التوفر</th><th>المواد</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        @forelse($services as $service)
            @php
                $availability = $service->branchServices->first();
                $price = $service->prices->first()?->price ?? $availability?->default_price;
            @endphp
            <tr>
                <td>{{ $service->code }}</td>
                <td><a href="{{ route('services.show', $service) }}">{{ $service->name }}</a></td>
                <td>{{ $service->category?->name ?? '—' }}</td>
                <td>{{ $price !== null ? number_format((float) $price, 2) : 'غير مسعّرة' }}</td>
                <td>{{ $service->prices->first()?->minimum_price ?? $availability?->minimum_price ?? '—' }}</td>
                <td>{{ $service->prices->isNotEmpty() ? 'سعر الفرع الساري' : ($availability?->default_price !== null ? 'السعر الافتراضي للفرع' : '—') }}</td>
                <td>{{ $service->prices->first()?->estimated_duration_minutes ?? $availability?->default_duration_minutes ?? $service->default_duration_minutes ?? '—' }}</td>
                <td>{{ $availability?->is_available ? 'متاحة' : 'غير متاحة' }}</td>
                <td>{{ $service->material_requirements_count }}</td>
                <td><x-status-badge :status="$service->is_active ? 'active' : 'inactive'" /></td>
                <td><a href="{{ route('services.show', $service) }}">عرض</a></td>
            </tr>
        @empty
            <tr><td colspan="11">لا توجد خدمات حتى الآن.</td></tr>
        @endforelse
        </tbody>
    </x-table-shell>
@endif

@if($permissions['service_packages.view'])
    <x-table-shell title="أحدث باقات الخدمات" :description="$branch ? 'السعر المباشر للفرع: '.$branch->name : 'لا يوجد فرع متاح لعرض الأسعار.'">
        <x-slot:tools><a href="{{ route('service-packages.index') }}">عرض الكل</a></x-slot:tools>
        <thead><tr><th>الكود</th><th>الباقة</th><th>الخدمات</th><th>العدد</th><th>السعر</th><th>الحد الأدنى</th><th>التوفر</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        @forelse($packages as $package)
            <tr>
                <td>{{ $package->code }}</td>
                <td>{{ $package->name }}</td>
                <td>{{ $package->items->pluck('service.name')->filter()->join('، ') ?: 'لا توجد خدمات' }}</td>
                <td>{{ $package->items_count }}</td>
                <td>{{ $package->branchPrices->first()?->price !== null ? number_format((float) $package->branchPrices->first()->price, 2) : 'غير مسعّرة' }}</td>
                <td>{{ $package->branchPrices->first()?->minimum_price ?? '—' }}</td>
                <td>{{ $package->branchPrices->first()?->is_available ? 'متاحة' : 'غير متاحة' }}</td>
                <td><x-status-badge :status="$package->is_active ? 'active' : 'inactive'" /></td>
                <td>@if($permissionNames->contains('service_packages.update'))<a href="{{ route('service-packages.edit', $package) }}">تعديل</a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="9">لا توجد باقات خدمات حتى الآن.</td></tr>
        @endforelse
        </tbody>
    </x-table-shell>
@endif

<div class="catalog-reference-grid">
    @if($permissions['service_categories.view'])
        <x-table-shell title="أحدث تصنيفات الخدمات">
            <thead><tr><th>الكود</th><th>التصنيف</th><th>الخدمات</th><th>الحالة</th></tr></thead>
            <tbody>@forelse($serviceCategories as $category)<tr><td>{{ $category->code }}</td><td>{{ $category->name }}</td><td>{{ $category->services_count }}</td><td><x-status-badge :status="$category->is_active ? 'active' : 'inactive'" /></td></tr>@empty<tr><td colspan="4">لا توجد تصنيفات.</td></tr>@endforelse</tbody>
        </x-table-shell>
    @endif
    @if($permissions['products.view'])
        <x-table-shell title="تصنيفات المنتجات والعلامات">
            <thead><tr><th>النوع</th><th>الاسم</th><th>المنتجات</th><th>الحالة</th></tr></thead>
            <tbody>
                @foreach($productCategories as $category)<tr><td>تصنيف</td><td>{{ $category->name }}</td><td>{{ $category->products_count }}</td><td><x-status-badge :status="$category->is_active ? 'active' : 'inactive'" /></td></tr>@endforeach
                @foreach($productBrands as $brand)<tr><td>علامة</td><td>{{ $brand->name }}</td><td>{{ $brand->products_count }}</td><td><x-status-badge :status="$brand->is_active ? 'active' : 'inactive'" /></td></tr>@endforeach
                @if($productCategories->isEmpty() && $productBrands->isEmpty())<tr><td colspan="4">لا توجد بيانات مرجعية.</td></tr>@endif
            </tbody>
        </x-table-shell>
    @endif
</div>
@endsection
