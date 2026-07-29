@extends('layouts.app')
@section('title','ربط الحسابات') @section('page-title','ربط الحسابات')
@section('content')
<div class="configuration-page">
@if(auth()->user()->hasPermission('accounting.mappings.payment_methods'))
<form class="sw-card sw-form" method="POST" action="{{ route('accounting.mappings.payment-methods') }}">@csrf<h3>وسيلة الدفع</h3><div class="sw-form-grid">
<label>الفرع ID<input type="number" name="branch_id" required></label><label>الوسيلة<select name="payment_method_id">@foreach($paymentMethods as $method)<option value="{{ $method->id }}">{{ $method->name }}</option>@endforeach</select></label>
<label>الحساب<select name="account_id">@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select></label></div><button class="sw-btn">حفظ</button></form>
@endif
@if(auth()->user()->hasPermission('accounting.mappings.products'))
<form class="sw-card sw-form" method="POST" action="{{ route('accounting.mappings.products') }}">@csrf<h3>المنتج</h3><div class="sw-form-grid">
<label>المنتج<select name="product_id">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select></label>
@foreach(['inventory_account_id'=>'المخزون','revenue_account_id'=>'الإيراد','cogs_account_id'=>'التكلفة','purchase_return_account_id'=>'مردود الشراء','adjustment_account_id'=>'التسوية'] as $field=>$label)<label>{{ $label }}<select name="{{ $field }}"><option value="">Default</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select></label>@endforeach
</div><button class="sw-btn">حفظ</button></form>@endif
</div>
@endsection
