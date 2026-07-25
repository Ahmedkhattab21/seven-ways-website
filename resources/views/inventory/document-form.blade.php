@extends('layouts.app')
@php($titles = ['openings'=>'رصيد افتتاحي','adjustments'=>'تسوية مخزون','counts'=>'جرد مخزون'])
@section('title', 'إضافة '.$titles[$section])
@section('page-title', 'إضافة '.$titles[$section])
@section('breadcrumb', 'المخزون / '.$titles[$section])
@section('content')
<x-card title="بيانات المسودة"><form class="sw-form" method="POST" action="{{ route('inventory.documents.store', $section) }}">@csrf
<div class="sw-form-grid">
<x-form.select name="warehouse_id" label="المخزن" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</x-form.select>
<x-form.input name="date" type="date" label="التاريخ" :value="today()->toDateString()" required />
@if($section !== 'counts')
<x-form.select name="product_id" label="المنتج" required>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }} ({{ $product->tracking_type }})</option>@endforeach</x-form.select>
<x-form.input name="quantity" type="number" step="0.000001" label="الكمية" /><x-form.input name="unit_cost" type="number" step="0.0001" label="تكلفة الوحدة" />
@endif
@if($section === 'openings')<x-form.input name="roll_number" label="رقم الرول" /><x-form.input name="roll_width" type="number" step="0.000001" label="عرض الرول" /><x-form.input name="roll_length" type="number" step="0.000001" label="طول الرول" />@endif
@if($section === 'adjustments')<x-form.select name="adjustment_type" label="نوع التسوية">@foreach(['increase','decrease','damage','expiry','correction'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</x-form.select><x-form.input name="reason" label="السبب" required />@endif
@if($section === 'counts')<x-form.select name="scope_type" label="النطاق"><option value="full">شامل</option><option value="category">تصنيف</option></x-form.select>@endif
<x-form.input name="notes" label="ملاحظات" />
</div><div class="sw-form-actions"><x-button type="submit">حفظ المسودة</x-button></div></form></x-card>
@endsection
