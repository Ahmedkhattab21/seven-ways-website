@extends('layouts.app')
@section('title', 'المنتجات')
@section('page-title', 'المنتجات')
@section('breadcrumb', 'المنتجات')
@section('page-description', 'إدارة المنتجات المتاحة وأسعارها ومخزونها حسب الفرع.')
@section('content')
<div class="catalog-page">
    <div class="catalog-quick-actions">
        @if($permissionNames->contains('products.create'))
            <a class="sw-button sw-button--primary" href="{{ route('products.create') }}">إضافة منتج</a>
        @endif
    </div>

    @if($branches->isNotEmpty())
        <x-card title="منتجات الفرع">
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
        <a class="catalog-summary__card" href="{{ route('products.index') }}">
            <span>المنتجات</span>
            <strong>{{ $summary['active'] }} / {{ $summary['total'] }}</strong>
            <small>متاح / إجمالي في الفرع</small>
        </a>
    </div>

    <x-table-shell title="منتجات الفرع" description="الإتاحة والسعر والمخزون تخص الفرع المحدد.">
        <x-slot:tools><a href="{{ route('products.index') }}">عرض الكل</a></x-slot:tools>
        <thead>
            <tr>
                <th>SKU</th>
                <th>المنتج</th>
                <th>السعر الأساسي</th>
                <th>العرض</th>
                <th>السعر النهائي</th>
                <th>المخزون المتاح</th>
                <th>المستودع</th>
                <th>الحالة</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @forelse($products as $product)
            <tr>
                <td>{{ $product->sku }}</td>
                <td>{{ $product->name }}</td>
                <td>{{ $product->resolved_branch_price ? number_format((float) $product->resolved_branch_price['base_price'], 2) : 'غير مسعّر' }}</td>
                <td>{{ $product->resolved_branch_price['promotion_name'] ?? '—' }}</td>
                <td>{{ $product->resolved_branch_price ? number_format((float) $product->resolved_branch_price['final_price'], 2) : '—' }}</td>
                <td>{{ number_format((float) $product->stockBalances->sum('available_quantity'), 2) }}</td>
                <td>{{ $product->branchProducts->first()?->defaultSalesWarehouse?->name ?? 'غير محدد' }}</td>
                <td><x-status-badge :status="$product->is_active ? 'active' : 'inactive'" /></td>
                <td>
                    @if($permissionNames->contains('products.manage_branch_availability') && $branch)
                        <a href="{{ route('products.branch-settings.edit', [$product, $branch]) }}">إتاحة وتسعير</a>
                    @elseif($permissionNames->contains('products.update'))
                        <a href="{{ route('products.edit', $product) }}">تعديل</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9">لا توجد منتجات متاحة لهذا الفرع حتى الآن.</td></tr>
        @endforelse
        </tbody>
    </x-table-shell>
</div>
@endsection
