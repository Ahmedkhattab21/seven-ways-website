@extends('layouts.app')
@section('title','دفعة مورد جديدة') @section('page-title','دفعة مورد جديدة')
@section('content')
<form class="sw-card sw-form" method="POST" action="{{ route('supplier-payments.store') }}">@csrf
<div class="sw-form-grid"><label>المورد<select name="supplier_id">@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></label><label>طريقة الدفع<select name="payment_method_id">@foreach($methods as $method)<option value="{{ $method->id }}">{{ $method->name }}</option>@endforeach</select></label><label>التاريخ<input type="date" name="payment_date" value="{{ today()->toDateString() }}" required></label><label>المبلغ<input type="number" step=".0001" min=".0001" name="amount" required></label><label>المرجع<input name="reference_number"></label></div><label>ملاحظات<textarea name="notes"></textarea></label><button class="sw-btn">حفظ</button>
</form>
@endsection
