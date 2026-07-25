@extends('layouts.app')
@section('title','مرتجع مشتريات جديد') @section('page-title','مرتجع مشتريات جديد')
@section('content')
<form class="sw-card sw-form" method="POST" action="{{ route('purchase-returns.store') }}">@csrf
<div class="sw-form-grid"><label>المورد<select name="supplier_id">@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></label><label>المخزن<select name="warehouse_id">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></label><label>الاستلام<select name="goods_receipt_id"><option value="">—</option>@foreach($receipts as $receipt)<option value="{{ $receipt->id }}">{{ $receipt->goods_receipt_number }}</option>@endforeach</select></label><label>التاريخ<input type="date" name="return_date" value="{{ today()->toDateString() }}" required></label></div><label>السبب<textarea name="reason" required></textarea></label>
<div class="sw-form-grid"><label>رقم سطر الاستلام<input type="number" name="items[0][goods_receipt_item_id]" required></label><label>رقم الوحدة<input type="number" name="items[0][unit_id]"></label><label>الكمية<input type="number" step=".000001" name="items[0][quantity]" required></label><label>سبب السطر<select name="items[0][reason_code]"><option value="damaged">تالف</option><option value="quality_failure">فشل جودة</option><option value="wrong_item">صنف خطأ</option><option value="other">أخرى</option></select></label></div>
<button class="sw-btn">حفظ المسودة</button></form>
@endsection
