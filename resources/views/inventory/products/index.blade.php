@extends('layouts.app')
@section('title', 'المنتجات')
@section('page-title', 'المنتجات')
@section('breadcrumb', 'المخزون / المنتجات')
@section('page-actions')
@if(auth()->user()->hasPermission('products.create'))<a class="sw-button sw-button--primary" href="{{ route('products.create') }}">إضافة منتج</a>@endif
@endsection
@section('content')
<x-card title="البحث والفلاتر">
    <form method="GET" class="sw-form"><div class="sw-form-grid">
        <x-form.input name="search" label="بحث" :value="request('search')" placeholder="الاسم أو SKU" />
        <x-form.select name="tracking_type" label="نوع التتبع"><option value="">الكل</option>@foreach(['quantity'=>'كمية','roll'=>'رول','batch'=>'دفعة','serial'=>'Serial'] as $value=>$label)<option value="{{ $value }}" @selected(request('tracking_type')===$value)>{{ $label }}</option>@endforeach</x-form.select>
    </div><div class="sw-form-actions"><x-button type="submit">تطبيق</x-button></div></form>
</x-card>
<x-table-shell>
    <thead><tr><th>SKU</th><th>المنتج</th><th>التصنيف</th><th>العلامة</th><th>التتبع</th><th>وحدة المخزون</th><th>الحالة</th><th></th></tr></thead>
    <tbody>@forelse($products as $product)<tr>
        <td>{{ $product->sku }}</td><td>{{ $product->name }}</td><td>{{ $product->category?->name }}</td>
        <td>{{ $product->brand?->name ?? '—' }}</td><td>{{ $product->tracking_type }}</td><td>{{ $product->stockUnit?->symbol }}</td>
        <td><x-status-badge :status="$product->is_active ? 'active' : 'inactive'" /></td>
        <td>@if(auth()->user()->hasPermission('products.update'))<a href="{{ route('products.edit', $product) }}">تعديل</a>@endif</td>
    </tr>@empty<tr><td colspan="8">لا توجد منتجات.</td></tr>@endforelse</tbody>
    <x-slot:footer>{{ $products->links() }}</x-slot:footer>
</x-table-shell>
@endsection
