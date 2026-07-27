@extends('layouts.app')
@section('title', 'ربط وسائل الدفع')
@section('page-title', 'توجيه وسائل الدفع')
@section('content')
@if(auth()->user()->hasPermission('treasury.mappings.update'))
<form class="sw-card" method="POST" action="{{ route('treasury.mappings.store') }}">@csrf
<div class="sw-form-grid"><select name="payment_method_id" required>@foreach($paymentMethods as $method)<option value="{{ $method->id }}">{{ $method->name }}</option>@endforeach</select><select name="branch_id"><option value="">Company Default</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select><select name="operation_type">@foreach(['receipt','payment','refund','deposit','withdrawal','transfer','merchant_settlement'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select><select name="account_id"><option value="">GL مباشر</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }}</option>@endforeach</select><select name="bank_account_id"><option value="">حساب بنكي</option>@foreach($bankAccounts as $account)<option value="{{ $account->id }}">{{ $account->account_name }}</option>@endforeach</select><select name="cash_box_id"><option value="">خزينة</option>@foreach($cashBoxes as $box)<option value="{{ $box->id }}">{{ $box->name }}</option>@endforeach</select></div><button class="sw-btn">حفظ التوجيه</button></form>
@endif
<div class="sw-card"><table class="sw-table"><thead><tr><th>وسيلة الدفع</th><th>الفرع</th><th>العملية</th><th>الهدف</th></tr></thead><tbody>@foreach($mappings as $mapping)<tr><td>{{ $mapping->payment_method_id }}</td><td>{{ $mapping->branch_id ?: 'Default' }}</td><td>{{ $mapping->operation_type }}</td><td>{{ $mapping->account?->account_code ?? $mapping->bankAccount?->account_name ?? $mapping->cashBox?->name }}</td></tr>@endforeach</tbody></table></div>
@endsection
