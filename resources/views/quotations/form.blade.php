@extends('layouts.app')
@section('title',$quotation->exists?'تعديل عرض السعر':'إضافة عرض سعر')
@section('content')
<div class="sw-page-header"><div><h1>{{ $quotation->exists?'تعديل Draft':'إضافة عرض سعر' }}</h1><p>الحساب النهائي يتم في Backend ويحفظ Snapshot.</p></div></div>
<form class="sw-card sw-form" method="POST" action="{{ $quotation->exists?route('quotations.update',$quotation):route('quotations.store') }}">@csrf @if($quotation->exists)@method('PUT')@endif
<div class="sw-form-grid">
<label>الفرع<select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id',$quotation->branch_id)==$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
<label>العميل<select name="customer_id" required>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id',$quotation->customer_id)==$customer->id)>{{ $customer->name }}</option>@endforeach</select></label>
<label>السيارة<select name="vehicle_id" required>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected(old('vehicle_id',$quotation->vehicle_id)==$vehicle->id)>{{ $vehicle->plate_number ?: $vehicle->vin }} — {{ $vehicle->customer_id }}</option>@endforeach</select></label>
<label>العملة<select name="currency_id" required>@foreach($currencies as $currency)<option value="{{ $currency->id }}" @selected(old('currency_id',$quotation->currency_id)==$currency->id)>{{ $currency->code }}</option>@endforeach</select></label>
<label>تاريخ العرض<input type="date" name="quotation_date" value="{{ old('quotation_date',$quotation->quotation_date?->format('Y-m-d')??today()->format('Y-m-d')) }}" required></label>
<label>صالح حتى<input type="date" name="valid_until" value="{{ old('valid_until',$quotation->valid_until?->format('Y-m-d')??today()->addDays(7)->format('Y-m-d')) }}" required></label>
<input type="hidden" name="lead_id" value="{{ old('lead_id',$leadId) }}">
<label>خصم عام<select name="discount_type"><option value="">بدون</option><option value="fixed" @selected(old('discount_type',$quotation->discount_type)==='fixed')>مبلغ</option><option value="percentage" @selected(old('discount_type',$quotation->discount_type)==='percentage')>نسبة</option></select></label>
<label>قيمة الخصم<input type="number" step="0.0001" min="0" name="discount_value" value="{{ old('discount_value',$quotation->discount_value??0) }}"></label>
</div>
<h2>العناصر</h2>
@php($rows=old('items',$quotation->exists?$quotation->items->toArray():[['item_type'=>'service','quantity'=>1]]))
@foreach($rows as $i=>$row)<fieldset class="sw-card"><div class="sw-form-grid">
<label>النوع<select name="items[{{ $i }}][item_type]">@foreach(['service'=>'خدمة','package'=>'باقة','product'=>'منتج','custom'=>'مخصص'] as $value=>$label)<option value="{{ $value }}" @selected(($row['item_type']??'service')===$value)>{{ $label }}</option>@endforeach</select></label>
<label>الخدمة<select name="items[{{ $i }}][service_id]"><option value="">—</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected(($row['service_id']??null)==$service->id)>{{ $service->name }}</option>@endforeach</select></label>
<label>الباقة<select name="items[{{ $i }}][service_package_id]"><option value="">—</option>@foreach($packages as $package)<option value="{{ $package->id }}" @selected(($row['service_package_id']??null)==$package->id)>{{ $package->name }}</option>@endforeach</select></label>
<label>المنتج<select name="items[{{ $i }}][product_id]"><option value="">—</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(($row['product_id']??null)==$product->id)>{{ $product->name }}</option>@endforeach</select></label>
<label>الوصف<input name="items[{{ $i }}][description]" value="{{ $row['description']??'' }}"></label>
<label>الكمية<input type="number" step="0.000001" min="0.000001" name="items[{{ $i }}][quantity]" value="{{ $row['quantity']??1 }}" required></label>
<label>سعر يدوي<input type="number" step="0.0001" min="0" name="items[{{ $i }}][manual_unit_price]" value="{{ $row['manual_unit_price']??'' }}"></label>
<label>خصم العنصر<input type="number" step="0.0001" min="0" name="items[{{ $i }}][discount_value]" value="{{ $row['discount_value']??0 }}"></label>
<label>نوع الخصم<select name="items[{{ $i }}][discount_type]"><option value="">بدون</option><option value="fixed" @selected(($row['discount_type']??null)==='fixed')>مبلغ</option><option value="percentage" @selected(($row['discount_type']??null)==='percentage')>نسبة</option></select></label>
</div></fieldset>@endforeach
<label>ملاحظات العميل<textarea name="customer_notes">{{ old('customer_notes',$quotation->customer_notes) }}</textarea></label><label>ملاحظات داخلية<textarea name="internal_notes">{{ old('internal_notes',$quotation->internal_notes) }}</textarea></label><label>الشروط<textarea name="terms_and_conditions">{{ old('terms_and_conditions',$quotation->terms_and_conditions) }}</textarea></label>
<button class="sw-btn sw-btn--primary">حفظ وحساب Snapshot</button></form>
@endsection
