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
        <x-form.select name="category_id" label="التصنيف"><option value="">الكل</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category_id')==$category->id)>{{ $category->name }}</option>@endforeach</x-form.select>
        <x-form.select name="brand_id" label="العلامة"><option value="">الكل</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected(request('brand_id')==$brand->id)>{{ $brand->name }}</option>@endforeach</x-form.select>
        <x-form.select name="status" label="الحالة"><option value="">الكل</option><option value="active" @selected(request('status')==='active')>نشط</option><option value="inactive" @selected(request('status')==='inactive')>معطل</option></x-form.select>
        <x-form.select name="is_sellable" label="قابل للبيع"><option value="">الكل</option><option value="1" @selected(request('is_sellable')==='1')>نعم</option><option value="0" @selected(request('is_sellable')==='0')>لا</option></x-form.select>
    </div><div class="sw-form-actions"><x-button type="submit">تطبيق</x-button></div></form>
</x-card>
<x-table-shell>
    <thead><tr><th>SKU</th><th>المنتج</th><th>التصنيف</th><th>العلامة</th><th>التتبع</th><th>وحدة المخزون</th><th>سعر البيع</th><th>الضريبة</th><th>بيع</th><th>شراء</th><th>استهلاك</th><th>الحالة</th><th></th></tr></thead>
    <tbody>@forelse($products as $product)<tr>
        <td>{{ $product->sku }}</td><td>{{ $product->name }}</td><td>{{ $product->category?->name }}</td>
        <td>{{ $product->brand?->name ?? '—' }}</td><td>{{ $product->tracking_type }}</td><td>{{ $product->stockUnit?->symbol }}</td>
        <td>{{ $product->default_sale_price !== null ? number_format((float) $product->default_sale_price, 2) : 'غير مسعّر' }}</td><td>{{ $product->defaultTax?->name ?? '—' }}</td>
        <td>{{ $product->is_sellable ? 'نعم' : 'لا' }}</td><td>{{ $product->is_purchasable ? 'نعم' : 'لا' }}</td><td>{{ $product->is_consumable ? 'نعم' : 'لا' }}</td>
        <td><x-status-badge :status="$product->is_active ? 'active' : 'inactive'" /></td>
        <td>@if(auth()->user()->hasPermission('products.update'))<a href="{{ route('products.edit', $product) }}">تعديل</a>@endif</td>
    </tr>@empty<tr><td colspan="13">لا توجد منتجات.</td></tr>@endforelse</tbody>
    <x-slot:footer>{{ $products->links() }}</x-slot:footer>
</x-table-shell>
@endsection
