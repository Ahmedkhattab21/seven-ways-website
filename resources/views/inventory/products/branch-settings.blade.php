@extends('layouts.app')
@section('title', 'إتاحة وتسعير المنتج')
@section('page-title', 'إتاحة وتسعير المنتج')
@section('breadcrumb', 'المنتجات / إعدادات الفرع')
@section('content')
<x-card title="{{ $product->name }} — {{ $branch->name }}">
    <form method="POST" action="{{ route('products.branch-settings.update', [$product, $branch]) }}" class="sw-form">
        @csrf
        @method('PUT')
        <div class="sw-form-grid">
            <x-form.select name="default_sales_warehouse_id" label="مستودع البيع الافتراضي">
                <option value="">بدون مستودع افتراضي</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected(old('default_sales_warehouse_id', $branchProduct?->default_sales_warehouse_id) == $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.input type="number" step="0.000001" name="minimum_stock" label="الحد الأدنى للمخزون" :value="old('minimum_stock', $branchProduct?->minimum_stock)" />
            <x-form.input type="number" step="0.000001" name="maximum_stock" label="الحد الأقصى للمخزون" :value="old('maximum_stock', $branchProduct?->maximum_stock)" />
            <x-form.input type="number" step="0.000001" name="reorder_quantity" label="كمية إعادة الطلب" :value="old('reorder_quantity', $branchProduct?->reorder_quantity)" />
        </div>
        <input type="hidden" name="is_available" value="0">
        <x-form.checkbox name="is_available" label="متاح في الفرع" :checked="old('is_available', $branchProduct?->is_available ?? true)" />
        <input type="hidden" name="is_sellable" value="0">
        <x-form.checkbox name="is_sellable" label="قابل للبيع في الفرع" :checked="old('is_sellable', $branchProduct?->is_sellable ?? true)" />
        <x-form.textarea name="notes" label="ملاحظات">{{ old('notes', $branchProduct?->notes) }}</x-form.textarea>

        @if(auth()->user()->hasPermission('products.manage_branch_prices'))
            <h3>إضافة سعر فرع جديد</h3>
            <p class="sw-muted">كل تعديل سعر ينشئ سجلًا جديدًا ويحافظ على تاريخ الأسعار.</p>
            <div class="sw-form-grid">
                <x-form.input type="number" step="0.0001" name="price" label="سعر البيع" :value="old('price')" />
                <x-form.input type="number" step="0.0001" name="minimum_price" label="الحد الأدنى للسعر" :value="old('minimum_price')" />
                <x-form.input type="date" name="effective_from" label="ساري من" :value="old('effective_from')" />
                <x-form.input type="date" name="effective_to" label="ساري حتى" :value="old('effective_to')" />
                <x-form.input type="number" name="priority" label="الأولوية" :value="old('priority', 0)" min="0" />
            </div>
        @endif
        <div class="sw-form-actions"><x-button type="submit">حفظ إعدادات الفرع</x-button></div>
    </form>
</x-card>

<x-card title="تاريخ الأسعار">
    <div class="sw-table-wrap"><table class="sw-table"><thead><tr><th>السعر</th><th>الحد الأدنى</th><th>من</th><th>إلى</th><th>الأولوية</th><th>الحالة</th></tr></thead>
    <tbody>@forelse($prices as $price)<tr><td>{{ number_format((float) $price->price, 2) }}</td><td>{{ $price->minimum_price === null ? '—' : number_format((float) $price->minimum_price, 2) }}</td><td>{{ $price->effective_from?->format('Y-m-d') }}</td><td>{{ $price->effective_to?->format('Y-m-d') ?? 'مفتوح' }}</td><td>{{ $price->priority }}</td><td>{{ $price->is_active ? 'نشط' : 'معطل' }}</td></tr>@empty<tr><td colspan="6">لا يوجد تاريخ أسعار لهذا الفرع.</td></tr>@endforelse</tbody></table></div>
</x-card>
@endsection
