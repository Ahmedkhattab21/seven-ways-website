@extends('layouts.app')
@section('title', $transfer->exists ? 'تعديل تحويل' : 'طلب تحويل')
@section('page-title', $transfer->exists ? 'تعديل تحويل' : 'طلب تحويل')
@section('breadcrumb', 'المخزون / التحويلات')
@section('content')
<x-card title="بيانات التحويل"><form class="sw-form" method="POST" action="{{ $transfer->exists ? route('stock-transfers.update', $transfer) : route('stock-transfers.store') }}">@csrf @if($transfer->exists) @method('PUT') @endif
<div class="sw-form-grid">
<x-form.select name="from_warehouse_id" label="مخزن المصدر" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(old('from_warehouse_id',$transfer->from_warehouse_id)==$warehouse->id)>{{ $warehouse->branch?->name }} / {{ $warehouse->name }}</option>@endforeach</x-form.select>
<x-form.select name="to_warehouse_id" label="مخزن الوجهة" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(old('to_warehouse_id',$transfer->to_warehouse_id)==$warehouse->id)>{{ $warehouse->branch?->name }} / {{ $warehouse->name }}</option>@endforeach</x-form.select>
<x-form.input name="expected_delivery_at" type="datetime-local" label="التسليم المتوقع" :value="old('expected_delivery_at',optional($transfer->expected_delivery_at)->format('Y-m-d\TH:i'))" />
<x-form.input name="notes" label="ملاحظات" :value="old('notes',$transfer->notes)" />
</div>
@php($lines = $transfer->exists ? $transfer->items : collect([null]))
@foreach($lines as $index=>$line)
<x-card :title="'العنصر '.($index+1)"><div class="sw-form-grid">
<x-form.select :name="'items['.$index.'][product_id]'" label="المنتج" required>@foreach($products as $product)<option value="{{ $product->id }}" @selected(old('items.'.$index.'.product_id',$line?->product_id)==$product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</x-form.select>
<x-form.select :name="'items['.$index.'][item_type]'" label="نوع العنصر" required>@foreach(['quantity'=>'كمية','roll'=>'رول كامل','scrap'=>'قصاصة كاملة'] as $value=>$label)<option value="{{ $value }}" @selected(old('items.'.$index.'.item_type',$line?->item_type ?? 'quantity')===$value)>{{ $label }}</option>@endforeach</x-form.select>
<x-form.input :name="'items['.$index.'][requested_quantity]'" type="number" step="0.000001" label="الكمية" :value="old('items.'.$index.'.requested_quantity',$line?->requested_quantity ?? 1)" />
<x-form.select :name="'items['.$index.'][roll_id]'" label="الرول"><option value="">—</option>@foreach($rolls as $roll)<option value="{{ $roll->id }}" @selected(old('items.'.$index.'.roll_id',$line?->roll_id)==$roll->id)>{{ $roll->roll_number }}</option>@endforeach</x-form.select>
<x-form.select :name="'items['.$index.'][scrap_id]'" label="القصاصة"><option value="">—</option>@foreach($scraps as $scrap)<option value="{{ $scrap->id }}" @selected(old('items.'.$index.'.scrap_id',$line?->scrap_id)==$scrap->id)>{{ $scrap->scrap_code }}</option>@endforeach</x-form.select>
</div></x-card>
@endforeach
<div class="sw-form-actions"><x-button type="submit">حفظ المسودة</x-button></div></form></x-card>
@endsection
