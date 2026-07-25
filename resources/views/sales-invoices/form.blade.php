@extends('layouts.app')
@section('title','بيع مباشر') @section('page-title','فاتورة بيع مباشر')
@section('content')
<form class="sw-card" method="POST" action="{{ route('sales-invoices.store') }}">@csrf
<select name="customer_id" required>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select>
<input type="date" name="invoice_date" value="{{ today()->toDateString() }}" required><input type="date" name="due_date">
<h2>العناصر</h2><div id="invoice-items"><div><select name="items[0][item_type]"><option value="product">منتج</option><option value="custom">مخصص</option></select><select name="items[0][product_id]"><option value="">-- المنتج --</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select><select name="items[0][warehouse_id]">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select><input name="items[0][description]" placeholder="الوصف"><input type="number" step="0.000001" name="items[0][quantity]" value="1" required><input type="number" step="0.0001" name="items[0][unit_price]" placeholder="السعر"></div></div>
<button class="sw-btn">حفظ Draft</button></form>
@endsection
