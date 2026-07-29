@extends('layouts.app')
@section('title', $product->exists ? 'تعديل منتج' : 'إضافة منتج')
@section('page-title', $product->exists ? 'تعديل منتج' : 'إضافة منتج')
@section('breadcrumb', 'المخزون / المنتجات')
@section('content')
<x-catalog-navigation active="products" />
<x-card title="بيانات المنتج">
<form class="sw-form" method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}">
@csrf @if($product->exists) @method('PUT') @endif
<div class="sw-form-grid">
    <x-form.input name="sku" label="SKU" :value="old('sku', $product->sku)" required />
    <x-form.input name="barcode" label="الباركود" :value="old('barcode', $product->barcode)" />
    <x-form.input name="name" label="الاسم" :value="old('name', $product->name)" required />
    <x-form.select name="category_id" label="التصنيف" required>@foreach($categories as $item)<option value="{{ $item->id }}" @selected(old('category_id',$product->category_id)==$item->id)>{{ $item->name }}</option>@endforeach</x-form.select>
    <x-form.select name="brand_id" label="العلامة"><option value="">—</option>@foreach($brands as $item)<option value="{{ $item->id }}" @selected(old('brand_id',$product->brand_id)==$item->id)>{{ $item->name }}</option>@endforeach</x-form.select>
    <x-form.select name="product_type" label="نوع المنتج">@foreach(['ppf','thermal_insulation','tint','glass_protection','installation_material','consumable','accessory','tool','other'] as $value)<option value="{{ $value }}" @selected(old('product_type',$product->product_type)===$value)>{{ $value }}</option>@endforeach</x-form.select>
    <x-form.select name="tracking_type" label="نوع التتبع">@foreach(['quantity','roll','batch','serial'] as $value)<option value="{{ $value }}" @selected(old('tracking_type',$product->tracking_type ?? 'quantity')===$value)>{{ $value }}</option>@endforeach</x-form.select>
    <x-form.select name="costing_method" label="طريقة التكلفة">@foreach(['weighted_average','specific','standard'] as $value)<option value="{{ $value }}" @selected(old('costing_method',$product->costing_method ?? 'weighted_average')===$value)>{{ $value }}</option>@endforeach</x-form.select>
    @foreach(['purchase_unit_id'=>'وحدة الشراء','stock_unit_id'=>'وحدة المخزون','sale_unit_id'=>'وحدة البيع'] as $field=>$label)<x-form.select :name="$field" :label="$label">@foreach($units as $unit)<option value="{{ $unit->id }}" @selected(old($field,$product->{$field})==$unit->id)>{{ $unit->name }} ({{ $unit->symbol }})</option>@endforeach</x-form.select>@endforeach
    <x-form.select name="default_tax_id" label="الضريبة"><option value="">—</option>@foreach($taxes as $tax)<option value="{{ $tax->id }}" @selected(old('default_tax_id',$product->default_tax_id)==$tax->id)>{{ $tax->name }}</option>@endforeach</x-form.select>
    <x-form.input name="standard_cost" type="number" step="0.0001" label="التكلفة القياسية" :value="old('standard_cost',$product->standard_cost)" />
    <x-form.input name="default_sale_price" type="number" step="0.0001" label="سعر البيع" :value="old('default_sale_price',$product->default_sale_price)" />
    <x-form.input name="minimum_stock" type="number" step="0.000001" label="الحد الأدنى" :value="old('minimum_stock',$product->minimum_stock ?? 0)" required />
    <x-form.input name="maximum_stock" type="number" step="0.000001" label="الحد الأقصى" :value="old('maximum_stock',$product->maximum_stock)" />
    <x-form.input name="reorder_quantity" type="number" step="0.000001" label="كمية إعادة الطلب" :value="old('reorder_quantity',$product->reorder_quantity)" />
</div>
@foreach(['is_sellable'=>'قابل للبيع','is_purchasable'=>'قابل للشراء','is_consumable'=>'قابل للاستهلاك','is_active'=>'نشط'] as $field=>$label)<input type="hidden" name="{{ $field }}" value="0"><label><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field,$product->{$field} ?? true))> {{ $label }}</label>@endforeach
<div class="sw-form-actions"><x-button type="submit">حفظ</x-button><a class="sw-button sw-button--outline" href="{{ route('products.index') }}">إلغاء</a></div>
</form>
</x-card>
@endsection
