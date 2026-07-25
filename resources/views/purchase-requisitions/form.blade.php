@extends('layouts.app')
@section('title','طلب شراء جديد') @section('page-title','طلب شراء جديد')
@section('content')
<form class="sw-card sw-form" method="POST" action="{{ route('purchase-requisitions.store') }}">@csrf
<div class="sw-form-grid"><label>تاريخ الطلب<input type="date" name="request_date" value="{{ old('request_date',today()->toDateString()) }}" required></label><label>تاريخ الاحتياج<input type="date" name="required_date"></label><label>الأولوية<select name="priority"><option value="normal">عادي</option><option value="high">عالي</option><option value="urgent">عاجل</option><option value="low">منخفض</option></select></label><label>القسم<input name="department"></label></div>
<label>الغرض<textarea name="purpose" required></textarea></label>
<h2>الصنف</h2><div class="sw-form-grid"><label>المنتج<select name="items[0][product_id]">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select></label><label>وحدة الشراء<input type="number" name="items[0][unit_id]" required></label><label>الكمية<input type="number" step=".000001" min=".000001" name="items[0][requested_quantity]" required></label><label>التكلفة التقديرية<input type="number" step=".0001" min="0" name="items[0][estimated_unit_cost]"></label><label>المورد المفضل<select name="items[0][preferred_supplier_id]"><option value="">—</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></label></div>
<button class="sw-btn">حفظ المسودة</button></form>
@endsection
