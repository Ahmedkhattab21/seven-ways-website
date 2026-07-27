@extends('layouts.app')
@section('title', 'الخزائن النقدية')
@section('page-title', 'الخزائن النقدية وأمناؤها')
@section('content')
@if(auth()->user()->hasPermission('treasury.cash_boxes.create'))
<form class="sw-card" method="POST" action="{{ route('treasury.cash-boxes.store') }}">
    @csrf
    <h3>خزينة جديدة</h3>
    <div class="sw-form-grid"><select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select><input name="code" required placeholder="الكود"><input name="name" required placeholder="الاسم"><select name="currency_id" required>@foreach($currencies as $currency)<option value="{{ $currency->id }}">{{ $currency->code }}</option>@endforeach</select><select name="gl_account_id" required>@foreach($glAccounts->where('is_cash_account', true) as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select><input name="maximum_cash_limit" type="number" min="0" step="0.01" placeholder="الحد الأقصى"><label><input type="checkbox" name="is_primary" value="1"> رئيسية</label></div>
    <button class="sw-btn">حفظ كمسودة</button>
</form>
@endif
@foreach($cashBoxes as $box)<div class="sw-card"><h3>{{ $box->name }} — {{ $box->branch->name }}</h3><p>الحالة: {{ $box->status }} | الرصيد الدفتري: {{ $balances[$box->id]['book_balance'] }}</p>
@if(auth()->user()->hasPermission('treasury.cash_boxes.manage_custodians'))<form method="POST" action="{{ route('treasury.cash-boxes.custodians',$box) }}">@csrf<select name="user_id" required>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select><input type="date" name="valid_from" value="{{ now()->toDateString() }}" required><label><input type="checkbox" name="is_primary" value="1"> أمين رئيسي</label><button class="sw-btn">تعيين أمين</button></form>@endif
<ul>@foreach($box->custodians->where('is_active',true) as $custodian)<li>{{ $custodian->user->name }} {{ $custodian->is_primary ? '— رئيسي' : '' }}</li>@endforeach</ul></div>@endforeach
@endsection
