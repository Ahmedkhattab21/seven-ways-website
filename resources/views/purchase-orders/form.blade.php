@extends('layouts.app')
@section('title','أمر شراء جديد') @section('page-title','أمر شراء جديد')
@section('content')
<form class="sw-card sw-form" method="POST" action="{{ route('purchase-orders.store') }}">@csrf
<div class="sw-form-grid"><label>المورد<select name="supplier_id">@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></label><label>تاريخ الأمر<input type="date" name="order_date" value="{{ today()->toDateString() }}" required></label><label>التسليم المتوقع<input type="date" name="expected_delivery_date"></label><label>سعر الصرف<input type="number" step=".00000001" min=".00000001" name="exchange_rate" value="1" required></label><label>الشحن<input type="number" step=".0001" min="0" name="shipping_amount" value="0"></label><label>مصروفات أخرى<input type="number" step=".0001" min="0" name="other_charges" value="0"></label></div>
<h2>الصنف</h2><div class="sw-form-grid"><label>المنتج<select name="items[0][product_id]">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select></label><label>الكمية<input type="number" step=".000001" min=".000001" name="items[0][ordered_quantity]" required></label><label>سعر الوحدة<input type="number" step=".0001" min="0" name="items[0][unit_price]" required></label><label>الضريبة %<input type="number" step=".0001" min="0" max="100" name="items[0][tax_rate]" value="15"></label></div>
<button class="sw-btn">حفظ المسودة</button></form>
@endsection
